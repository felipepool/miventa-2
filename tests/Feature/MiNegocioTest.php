<?php

use App\Enums\Modulo;
use App\Enums\Plan;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('business endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([
    ['getJson', '/api/v1/negocio'], ['putJson', '/api/v1/negocio'],
    ['putJson', '/api/v1/negocio/modulos'], ['putJson', '/api/v1/negocio/preferencias'],
]);

test('operational users receive 403 when managing the business', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();
    $nombre = $usuario->negocio->nombre;
    $this->actingAs($usuario)->getJson('/api/v1/negocio')->assertForbidden();
    $this->putJson('/api/v1/negocio', ['nombre' => 'Tomado'])->assertForbidden();
    $this->putJson('/api/v1/negocio/modulos', ['modulos' => []])->assertForbidden();
    $this->putJson('/api/v1/negocio/preferencias', ['cierre_ciego' => false, 'descuento_maximo_cajero' => 100])->assertForbidden();
    expect($usuario->negocio->fresh())->nombre->toBe($nombre)->cierre_ciego->toBeTrue()->modulos->toHaveCount(3);
})->with(['cajero', 'cocina', 'garzon']);

test('business routes require a confirmed branch when several are available', function () {
    $admin = User::factory()->admin()->create();
    Sucursal::factory()->for($admin->negocio)->create();
    $this->actingAs($admin)->getJson('/api/v1/negocio')->assertStatus(409)->assertJsonPath('code', 'sucursal_requerida');
    $this->putJson('/api/v1/negocio', ['nombre' => 'No guardado'])->assertStatus(409);
    expect($admin->negocio->fresh()->nombre)->not->toBe('No guardado');
});

test('the admin sees only their own business configuration', function () {
    $admin = User::factory()->for(Negocio::factory()->plan(Plan::Retail)->conSucursalPrincipal())->admin()->create();
    $ajeno = Negocio::factory()->create(['nombre' => 'Ajeno']);
    $this->actingAs($admin)->getJson('/api/v1/negocio')->assertOk()
        ->assertJsonPath('data.codigo', $admin->negocio->codigo)
        ->assertJsonPath('data.plan.value', 'retail')
        ->assertJsonPath('data.modulos.0', [
            'value' => 'venta_por_peso', 'label' => 'Venta por medida', 'descripcion' => Modulo::VentaPorPeso->descripcion(),
            'incluido' => true, 'activo' => true,
        ])
        ->assertJsonPath('data.modulos.1.incluido', false)
        ->assertJsonPath('data.cierre_ciego', true)
        ->assertJsonPath('data.descuento_maximo_cajero', 10)
        ->assertJsonMissing(['nombre' => 'Ajeno']);
    expect($ajeno->fresh()->nombre)->toBe('Ajeno');
});

test('saving business data normalizes the rut and keeps the code fixed', function () {
    $admin = User::factory()->admin()->create();
    $codigo = $admin->negocio->codigo;
    $this->actingAs($admin)->putJson('/api/v1/negocio', [
        'codigo' => 'otro-codigo', 'nombre' => ' Almacén Rosa ', 'rut' => '12.345.678-5', 'razon_social' => 'Rosa SpA',
        'giro' => 'Almacén', 'direccion' => 'Av. Uno 123', 'telefono' => '+56 9 1234 5678', 'email' => 'rosa@example.com',
    ])->assertOk()->assertJsonPath('data.nombre', 'Almacén Rosa')->assertJsonPath('data.rut', '12345678-5');
    expect($admin->negocio->fresh())->codigo->toBe($codigo)->nombre->toBe('Almacén Rosa')
        ->rut->toBe('12345678-5')->email->toBe('rosa@example.com');
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.negocio.nombre', 'Almacén Rosa');
});

test('business data is validated', function (array $cambios, string $campo, string $mensaje) {
    $admin = User::factory()->admin()->create();
    Negocio::factory()->create(['rut' => '11111111-1']);
    $this->actingAs($admin)->putJson('/api/v1/negocio', [...['nombre' => 'Válido'], ...$cambios])
        ->assertUnprocessable()->assertJsonValidationErrors([$campo => $mensaje]);
    expect($admin->negocio->fresh()->nombre)->not->toBe('Válido');
})->with([
    'missing name' => [['nombre' => '   '], 'nombre', 'obligatorio'],
    'wrong check digit' => [['rut' => '12.345.678-9'], 'rut', 'no es válido'],
    'bad format' => [['rut' => 'abc'], 'rut', 'formato válido'],
    'rut of another business' => [['rut' => '11.111.111-1'], 'rut', 'Este RUT ya está registrado en otro negocio.'],
    'invalid email' => [['email' => 'no-es-correo'], 'email', 'correo'],
]);

test('the business may keep its own rut and clear optional data', function () {
    $admin = User::factory()->for(Negocio::factory()->conSucursalPrincipal()->state(['rut' => '12345678-5']))->admin()->create();
    $this->actingAs($admin)->putJson('/api/v1/negocio', ['nombre' => 'Mismo', 'rut' => '12345678-5'])->assertOk();
    $this->putJson('/api/v1/negocio', ['nombre' => 'Mismo', 'rut' => '', 'email' => null])->assertOk()->assertJsonPath('data.rut', null);
    expect($admin->negocio->fresh())->rut->toBeNull()->email->toBeNull();
});

test('modules can be switched off and on only within the plan', function () {
    $admin = User::factory()->for(Negocio::factory()->plan(Plan::Comida)->conSucursalPrincipal())->admin()->create();
    $this->actingAs($admin)->putJson('/api/v1/negocio/modulos', ['modulos' => ['comanda']])->assertOk()
        ->assertJsonPath('data.modulos.1.activo', false)->assertJsonPath('data.modulos.2.activo', true);
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.modulos', ['comanda']);
    $this->putJson('/api/v1/negocio/modulos', ['modulos' => ['comanda', 'venta_por_peso']])
        ->assertUnprocessable()->assertJsonValidationErrors(['modulos.1' => 'Tu plan no incluye este módulo.']);
    $this->putJson('/api/v1/negocio/modulos', [])->assertUnprocessable()->assertJsonValidationErrors('modulos');
    expect($admin->negocio->fresh()->modulosActivos())->toBe([Modulo::Comanda]);
    $this->putJson('/api/v1/negocio/modulos', ['modulos' => []])->assertOk();
    expect($admin->negocio->fresh()->modulosActivos())->toBe([]);
});

test('operation preferences are saved within 0 and 100 percent', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->putJson('/api/v1/negocio/preferencias', ['cierre_ciego' => false, 'descuento_maximo_cajero' => 100])->assertOk()
        ->assertJsonPath('data.cierre_ciego', false)->assertJsonPath('data.descuento_maximo_cajero', 100);
    expect($admin->negocio->fresh())->cierre_ciego->toBeFalse()->descuento_maximo_cajero->toBe(100);
});

test('operation preferences are validated', function (mixed $descuento) {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->putJson('/api/v1/negocio/preferencias', ['cierre_ciego' => false, 'descuento_maximo_cajero' => $descuento])
        ->assertUnprocessable()->assertJsonValidationErrors(['descuento_maximo_cajero' => 'descuento máximo del cajero']);
    expect($admin->negocio->fresh())->cierre_ciego->toBeTrue()->descuento_maximo_cajero->toBe(10);
})->with([-1, 101, 12.5, null]);
