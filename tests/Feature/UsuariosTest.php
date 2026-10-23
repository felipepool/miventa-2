<?php

use App\Enums\Rol;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosCreacionUsuario(array $cambios = []): array
{
    return [
        'name' => 'Juana Pérez',
        'username' => 'jperez',
        'email' => null,
        'roles' => ['cajero'],
        'sucursales' => [],
        'password' => 'clave-segura-123',
        'password_confirmation' => 'clave-segura-123',
        ...$cambios,
    ];
}

test('guests receive 401 when requesting user administration data', function (string $endpoint) {
    $this->getJson($endpoint)->assertUnauthorized();
})->with(['/api/v1/usuarios', '/api/v1/roles']);

test('cashiers receive 403 when requesting user administration data', function (string $endpoint) {
    $cajero = User::factory()->cajero()->create();

    $this->actingAs($cajero)->getJson($endpoint)->assertForbidden();
})->with(['/api/v1/usuarios', '/api/v1/roles']);

test('the list exposes public user data and all assigned roles and branches', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ana Administradora']);
    $usuario = User::factory()->for($admin->negocio)->conRoles(Rol::Cocina, Rol::Garzon)->create([
        'name' => 'Pedro Pérez',
        'username' => 'pedro',
        'email' => null,
    ]);
    $sucursal = Sucursal::factory()->for($admin->negocio)->create(['nombre' => 'Norte']);
    $usuario->sucursales()->attach($sucursal);

    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$sucursal->public_id)->assertOk();

    $this->actingAs($admin)->getJson('/api/v1/usuarios')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $admin->public_id)
        ->assertJsonPath('data.0.es_usuario_actual', true)
        ->assertJsonPath('data.1.id', $usuario->public_id)
        ->assertJsonPath('data.1.username', 'pedro')
        ->assertJsonPath('data.1.email', null)
        ->assertJsonPath('data.1.activo', true)
        ->assertJsonPath('data.1.roles', ['cocina', 'garzon'])
        ->assertJsonPath('data.1.es_usuario_actual', false)
        ->assertJsonPath('data.1.sucursales.0.nombre', 'Casa Matriz')
        ->assertJsonPath('data.1.sucursales.1.id', $sucursal->public_id)
        ->assertJsonMissingPath('data.1.negocio_id')
        ->assertJsonMissingPath('data.1.password')
        ->assertJsonMissingPath('data.1.remember_token')
        ->assertJsonMissingPath('data.1.sucursales.0.negocio_id');
});

test('search only matches users of the current business by name username or email', function (string $campo) {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create([
        $campo => $campo === 'email' ? 'coincidencia@example.com' : 'coincidencia',
    ]);
    User::factory()->cajero()->create([
        $campo => $campo === 'email' ? 'otra.coincidencia@example.com' : 'otra.coincidencia',
    ]);

    $this->actingAs($admin)->getJson('/api/v1/usuarios?buscar=coincidencia')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $usuario->public_id)
        ->assertJsonPath('meta.total', 1);
})->with(['name', 'username', 'email']);

test('users can be filtered by role status and their combination', function (string $consulta, array $esperados) {
    $admin = User::factory()->admin()->create(['name' => 'Ana']);
    User::factory()->for($admin->negocio)->cajero()->create(['name' => 'Bruno', 'username' => 'bruno']);
    User::factory()->for($admin->negocio)->conRoles(Rol::Cocina, Rol::Garzon)->create(['name' => 'Carla', 'username' => 'carla']);
    User::factory()->for($admin->negocio)->garzon()->inactivo()->create(['name' => 'Diego', 'username' => 'diego']);

    $response = $this->actingAs($admin)->getJson('/api/v1/usuarios?'.$consulta)
        ->assertOk()
        ->assertJsonCount(count($esperados), 'data');

    expect(array_column($response->json('data'), 'username'))->toBe($esperados);
})->with([
    'one of several roles matches' => ['rol=garzon', ['carla', 'diego']],
    'inactive accounts' => ['estado=inactivo', ['diego']],
    'active waiters' => ['rol=garzon&estado=activo', ['carla']],
]);

test('pagination preserves filters and has a stable order for equal names', function () {
    $admin = User::factory()->admin()->create();
    $usuarios = User::factory()->for($admin->negocio)->cajero()->count(16)->create(['name' => 'Cajero']);

    $this->actingAs($admin)->getJson('/api/v1/usuarios?rol=cajero&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $usuarios->last()->public_id)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('links.prev', fn (string $url): bool => str_contains($url, 'rol=cajero') && str_contains($url, 'page=1'));
});

test('a search without matches returns an empty paginated list', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/v1/usuarios?buscar=inexistente')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

test('invalid user filters return 422 with the affected field', function (string $consulta, string $campo) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/v1/usuarios?'.$consulta)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);
})->with([
    'unknown role' => ['rol=inexistente', 'rol'],
    'unknown status' => ['estado=inexistente', 'estado'],
    'search array' => ['buscar[]=texto', 'buscar'],
    'search too long' => ['buscar='.str_repeat('a', 101), 'buscar'],
    'negative page' => ['page=-1', 'page'],
    'non numeric page' => ['page=texto', 'page'],
]);

test('an admin receives the available roles with their labels', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/v1/roles')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['value' => 'admin', 'label' => 'Administrador'],
            ['value' => 'cajero', 'label' => 'Cajero'],
            ['value' => 'cocina', 'label' => 'Cocina'],
            ['value' => 'garzon', 'label' => 'Garzón'],
        ]]);
});

test('creating a user without a session returns 401 and saves nothing', function () {
    $this->postJson('/api/v1/usuarios', datosCreacionUsuario(['roles' => ['admin']]))
        ->assertUnauthorized();

    $this->assertDatabaseCount('users', 0);
});

test('operational roles receive 403 when creating users', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();

    $this->actingAs($usuario)->postJson('/api/v1/usuarios', datosCreacionUsuario(['roles' => ['admin']]))
        ->assertForbidden();

    $this->assertDatabaseCount('users', 1);
})->with(['cajero', 'cocina', 'garzon']);

test('an admin creates a user with several roles and branches and a hashed password', function () {
    $admin = User::factory()->admin()->create();
    $matriz = $admin->negocio->sucursales()->firstOrFail();
    $norte = Sucursal::factory()->for($admin->negocio)->create(['nombre' => 'Norte']);

    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$matriz->public_id)->assertOk();

    $response = $this->actingAs($admin)->postJson('/api/v1/usuarios', datosCreacionUsuario([
        'username' => '  JPerez  ',
        'email' => '   ',
        'roles' => ['garzon', 'cocina'],
        'sucursales' => [$norte->public_id, $matriz->public_id],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.username', 'jperez')
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.activo', true)
        ->assertJsonPath('data.roles', ['cocina', 'garzon'])
        ->assertJsonCount(2, 'data.sucursales')
        ->assertJsonPath('data.es_usuario_actual', false)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.negocio_id');

    $usuario = User::where('public_id', $response->json('data.id'))->firstOrFail();

    $this->assertDatabaseHas('users', ['id' => $usuario->id, 'negocio_id' => $admin->negocio_id, 'username' => 'jperez', 'email' => null]);
    $this->assertDatabaseHas('sucursal_user', ['user_id' => $usuario->id, 'sucursal_id' => $matriz->id]);
    $this->assertDatabaseHas('sucursal_user', ['user_id' => $usuario->id, 'sucursal_id' => $norte->id]);
    expect(Hash::check('clave-segura-123', $usuario->password))->toBeTrue();
    expect($usuario->hasAllRoles(['cocina', 'garzon']))->toBeTrue();
});

test('a new administrator has access to all branches without explicit assignments', function () {
    $admin = User::factory()->admin()->create();
    $matriz = $admin->negocio->sucursales()->firstOrFail();

    $response = $this->actingAs($admin)->postJson('/api/v1/usuarios', datosCreacionUsuario([
        'roles' => ['admin'],
        'sucursales' => [$matriz->public_id],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.roles', ['admin'])
        ->assertJsonCount(0, 'data.sucursales');

    $usuario = User::where('public_id', $response->json('data.id'))->firstOrFail();

    expect($usuario->sucursalesAccesibles()->modelKeys())->toBe([$matriz->id]);
    $this->assertDatabaseMissing('sucursal_user', ['user_id' => $usuario->id]);
});

test('creation ignores forged business and active attributes', function () {
    $admin = User::factory()->admin()->create();
    $otroAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson('/api/v1/usuarios', datosCreacionUsuario([
        'roles' => ['admin'],
        'negocio_id' => $otroAdmin->negocio_id,
        'activo' => false,
    ]))->assertCreated()->assertJsonPath('data.activo', true);

    $this->assertDatabaseHas('users', ['username' => 'jperez', 'negocio_id' => $admin->negocio_id, 'activo' => true]);
    $this->assertDatabaseMissing('users', ['username' => 'jperez', 'negocio_id' => $otroAdmin->negocio_id]);
});

test('a duplicate username in the same business returns 422 without creating another account', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->for($admin->negocio)->cajero()->create(['username' => 'jperez']);

    $this->actingAs($admin)->postJson('/api/v1/usuarios', datosCreacionUsuario(['roles' => ['admin'], 'username' => 'JPEREZ']))
        ->assertUnprocessable()->assertJsonValidationErrors('username');

    $this->assertDatabaseCount('users', 2);
});

test('the same username can be created in a different business', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->cajero()->create(['username' => 'jperez']);

    $this->actingAs($admin)->postJson('/api/v1/usuarios', datosCreacionUsuario(['roles' => ['admin']]))
        ->assertCreated()->assertJsonPath('data.username', 'jperez');

    $this->assertDatabaseHas('users', ['username' => 'jperez', 'negocio_id' => $admin->negocio_id]);
});

test('an email already used in another business returns 422', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->cajero()->create(['email' => 'juana@example.com']);

    $this->actingAs($admin)->postJson('/api/v1/usuarios', datosCreacionUsuario(['roles' => ['admin'], 'email' => 'juana@example.com']))
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->assertDatabaseCount('users', 2);
});

test('invalid creation data returns 422 and saves no user', function (array $cambios, string $campo) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson('/api/v1/usuarios', datosCreacionUsuario(['roles' => ['admin'], ...$cambios]))
        ->assertUnprocessable()->assertJsonValidationErrors($campo);

    $this->assertDatabaseCount('users', 1);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing username' => [['username' => ''], 'username'],
    'short username' => [['username' => 'ab'], 'username'],
    'long username' => [['username' => str_repeat('a', 31)], 'username'],
    'invalid username characters' => [['username' => 'juan perez'], 'username'],
    'username array' => [['username' => ['jperez']], 'username'],
    'invalid email' => [['email' => 'correo-invalido'], 'email'],
    'email array' => [['email' => ['juana@example.com']], 'email'],
    'empty roles' => [['roles' => []], 'roles'],
    'unknown role' => [['roles' => ['inexistente']], 'roles.0'],
    'duplicate roles' => [['roles' => ['cajero', 'cajero']], 'roles.0'],
    'admin combined with an operational role' => [['roles' => ['admin', 'garzon']], 'roles'],
    'operational user without branches' => [['roles' => ['garzon']], 'sucursales'],
    'short password' => [['password' => 'corta', 'password_confirmation' => 'corta'], 'password'],
    'password confirmation differs' => [['password_confirmation' => 'otra-clave'], 'password'],
]);

test('a foreign or inactive branch returns 422 without creating an account', function (string $caso) {
    $admin = User::factory()->admin()->create();
    $sucursal = match ($caso) {
        'another business' => Sucursal::factory()->create(),
        'inactive branch' => Sucursal::factory()->for($admin->negocio)->inactiva()->create(),
    };

    $this->actingAs($admin)->postJson('/api/v1/usuarios', datosCreacionUsuario(['sucursales' => [$sucursal->public_id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('sucursales.0');

    $this->assertDatabaseCount('users', 1);
})->with(['another business', 'inactive branch']);

/**
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosEdicionUsuario(User $usuario, array $cambios = []): array
{
    return [
        'name' => $usuario->name,
        'username' => $usuario->username,
        'email' => $usuario->email,
        'roles' => ['cajero'],
        'sucursales' => $usuario->sucursales()->pluck('sucursales.public_id')->all(),
        ...$cambios,
    ];
}

test('an admin reads user details including inactive branch assignments without exposing credentials', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();
    $inactiva = Sucursal::factory()->for($admin->negocio)->inactiva()->create();
    $usuario->sucursales()->sync([$inactiva->id]);

    $this->actingAs($admin)->getJson("/api/v1/usuarios/{$usuario->public_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $usuario->public_id)
        ->assertJsonPath('data.roles', ['cajero'])
        ->assertJsonPath('data.sucursales.0.activa', false)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.negocio_id');
});

test('user details and updates return 401 without a session', function (string $metodo) {
    $usuario = User::factory()->cajero()->create();
    $payload = datosEdicionUsuario($usuario, ['name' => 'No debe cambiar']);

    $this->{$metodo}("/api/v1/usuarios/{$usuario->public_id}", $payload)->assertUnauthorized();

    expect($usuario->fresh()->name)->toBe($usuario->name);
})->with(['getJson', 'putJson']);

test('user details and updates return 403 for operational roles', function (string $rol, string $metodo) {
    $editor = User::factory()->{$rol}()->create();
    $usuario = User::factory()->for($editor->negocio)->cajero()->create();
    $payload = datosEdicionUsuario($usuario, ['name' => 'No debe cambiar']);

    $this->actingAs($editor)->{$metodo}("/api/v1/usuarios/{$usuario->public_id}", $payload)->assertForbidden();

    expect($usuario->fresh()->name)->toBe($usuario->name);
})->with(['cajero', 'cocina', 'garzon'])->with(['getJson', 'putJson']);

test('user details and updates return 404 for another business or a numeric identifier', function (string $caso, string $metodo) {
    $admin = User::factory()->admin()->create();
    $usuario = $caso === 'foreign business'
        ? User::factory()->cajero()->create()
        : User::factory()->for($admin->negocio)->cajero()->create();
    $identificador = $caso === 'numeric id' ? $usuario->id : $usuario->public_id;

    $this->actingAs($admin)->{$metodo}("/api/v1/usuarios/{$identificador}", datosEdicionUsuario($usuario, ['name' => 'No debe cambiar']))
        ->assertNotFound();

    expect($usuario->fresh()->name)->toBe($usuario->name);
})->with(['foreign business', 'numeric id'])->with(['getJson', 'putJson']);

test('an admin updates personal data roles and branches without changing an omitted or blank password', function (array $contrasena) {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();
    $norte = Sucursal::factory()->for($admin->negocio)->create();
    $hashAnterior = $usuario->password;

    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$norte->public_id)->assertOk();

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$usuario->public_id}", datosEdicionUsuario($usuario, [
        'name' => 'Nombre actualizado', 'username' => ' ACTUALIZADO ', 'email' => ' actualizado@example.com ',
        'roles' => ['cocina', 'garzon'], 'sucursales' => [$norte->public_id], ...$contrasena,
    ]))->assertOk()->assertJsonPath('data.username', 'actualizado')
        ->assertJsonPath('data.email', 'actualizado@example.com')
        ->assertJsonPath('data.roles', ['cocina', 'garzon'])
        ->assertJsonPath('data.sucursales.0.id', $norte->public_id);

    expect($usuario->fresh()->name)->toBe('Nombre actualizado')
        ->and($usuario->fresh()->password)->toBe($hashAnterior)
        ->and($usuario->sucursales()->pluck('sucursales.id')->all())->toBe([$norte->id]);
})->with(['omitted password' => [[]], 'blank password' => [['password' => '', 'password_confirmation' => '']]]);

test('an admin resets a password and the old password no longer matches', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$usuario->public_id}", datosEdicionUsuario($usuario, [
        'password' => 'nueva-clave-123', 'password_confirmation' => 'nueva-clave-123',
    ]))->assertOk()->assertJsonMissingPath('data.password');

    expect(Hash::check('nueva-clave-123', $usuario->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $usuario->fresh()->password))->toBeFalse();
});

test('an admin edits their own data without losing their administrator role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$admin->public_id}", datosEdicionUsuario($admin, [
        'name' => 'Administradora actualizada', 'roles' => ['admin'],
    ]))->assertOk()->assertJsonPath('data.es_usuario_actual', true);

    $this->actingAs($admin->fresh())->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.name', 'Administradora actualizada')
        ->assertJsonPath('data.roles', ['admin']);
});

test('removing your own administrator role returns 422 and saves no changes', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->for($admin->negocio)->admin()->create(['username' => 'otro.admin']);
    $principal = $admin->negocio->sucursales()->first();

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$admin->public_id}", datosEdicionUsuario($admin, [
        'name' => 'No debe cambiar', 'sucursales' => [$principal->public_id],
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'roles' => 'No puedes quitarte el rol de administrador.',
    ]);

    expect($admin->fresh()->name)->toBe($admin->name)
        ->and($admin->fresh()->esAdmin())->toBeTrue();
});

test('removing the last active administrator role returns 422 even for a delegated user manager', function () {
    $admin = User::factory()->admin()->create();
    $editor = User::factory()->for($admin->negocio)->cajero()->create();
    setPermissionsTeamId($admin->negocio_id);
    $editor->givePermissionTo('gestionar_usuarios');
    $principal = $admin->negocio->sucursales()->first();

    $this->actingAs($editor)->putJson("/api/v1/usuarios/{$admin->public_id}", datosEdicionUsuario($admin, [
        'name' => 'No debe cambiar', 'sucursales' => [$principal->public_id],
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'roles' => 'El negocio debe tener al menos un administrador activo.',
    ]);

    expect($admin->fresh()->esAdmin())->toBeTrue()
        ->and($admin->fresh()->name)->toBe($admin->name);
});

test('an admin promotes an operational user and clears explicit branch assignments', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$usuario->public_id}", datosEdicionUsuario($usuario, ['roles' => ['admin']]))
        ->assertOk()->assertJsonPath('data.roles', ['admin'])->assertJsonCount(0, 'data.sucursales');

    expect($usuario->fresh()->esAdmin())->toBeTrue()
        ->and($usuario->sucursales()->count())->toBe(0);
});

test('an admin demotes another administrator when one active administrator remains', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->admin()->create(['username' => 'otro.admin']);
    $principal = $admin->negocio->sucursales()->first();

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$usuario->public_id}", datosEdicionUsuario($usuario, [
        'roles' => ['cajero'], 'sucursales' => [$principal->public_id],
    ]))->assertOk()->assertJsonPath('data.roles', ['cajero']);

    expect($usuario->fresh()->esAdmin())->toBeFalse()
        ->and($usuario->sucursales()->value('sucursales.id'))->toBe($principal->id);
});

test('editing preserves an already assigned inactive branch or removes it explicitly', function (bool $conservar) {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();
    $inactiva = Sucursal::factory()->for($admin->negocio)->inactiva()->create();
    $usuario->sucursales()->attach($inactiva);
    $asignaciones = $conservar ? $usuario->sucursales()->pluck('sucursales.public_id')->all() : [$admin->negocio->sucursales()->first()->public_id];

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$usuario->public_id}", datosEdicionUsuario($usuario, [
        'name' => 'Nombre actualizado', 'sucursales' => $asignaciones,
    ]))->assertOk();

    expect($usuario->sucursales()->whereKey($inactiva->id)->exists())->toBe($conservar);
})->with(['preserve' => true, 'remove' => false]);

test('editing rejects a foreign branch or a new inactive assignment with 422 and saves nothing', function (string $caso) {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();
    $sucursal = $caso === 'foreign branch' ? Sucursal::factory()->create() : Sucursal::factory()->for($admin->negocio)->inactiva()->create();
    $originales = $usuario->sucursales()->pluck('sucursales.id')->all();

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$usuario->public_id}", datosEdicionUsuario($usuario, [
        'name' => 'No debe cambiar', 'sucursales' => [$sucursal->public_id],
    ]))->assertUnprocessable()->assertJsonValidationErrors('sucursales.0');

    expect($usuario->fresh()->name)->toBe($usuario->name)
        ->and($usuario->sucursales()->pluck('sucursales.id')->all())->toBe($originales);
})->with(['foreign branch', 'new inactive branch']);

test('invalid update data returns 422 without changing personal data or the password', function (array $cambios, string $campo) {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();
    $hashAnterior = $usuario->password;

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$usuario->public_id}", datosEdicionUsuario($usuario, [
        'name' => 'No debe cambiar', ...$cambios,
    ]))->assertUnprocessable()->assertJsonValidationErrors($campo);

    expect($usuario->fresh()->name)->toBe($usuario->name)
        ->and($usuario->fresh()->password)->toBe($hashAnterior);
})->with([
    'empty roles' => [['roles' => []], 'roles'],
    'admin mixed with other roles' => [['roles' => ['admin', 'cajero']], 'roles'],
    'no branches' => [['sucursales' => []], 'sucursales'],
    'short new password' => [['password' => 'corta', 'password_confirmation' => 'corta'], 'password'],
    'missing password confirmation' => [['password' => 'clave-segura-123'], 'password'],
    'mismatched confirmation' => [['password' => 'clave-segura-123', 'password_confirmation' => 'otra'], 'password'],
]);

test('editing accepts unchanged unique fields but rejects another accounts username or email', function (string $campo) {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();
    $otro = User::factory()->for($admin->negocio)->cajero()->create();

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$usuario->public_id}", datosEdicionUsuario($usuario, [$campo => $otro->{$campo}]))
        ->assertUnprocessable()->assertJsonValidationErrors($campo);

    expect($usuario->fresh()->{$campo})->toBe($usuario->{$campo});
})->with(['username', 'email']);

test('editing cannot forge the business or change status through the update endpoint', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();

    $this->actingAs($admin)->putJson("/api/v1/usuarios/{$usuario->public_id}", datosEdicionUsuario($usuario, [
        'name' => 'Nombre actualizado', 'negocio_id' => 999999, 'activo' => false,
    ]))->assertOk()->assertJsonPath('data.activo', true);

    expect($usuario->fresh()->negocio_id)->toBe($admin->negocio_id)
        ->and($usuario->fresh()->activo)->toBeTrue();
});
