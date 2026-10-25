<?php

use App\Enums\Modulo;
use App\Enums\Plan;
use App\Models\GrupoModificador;
use App\Models\Modificador;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminModificadores(?Negocio $negocio = null): User
{
    return User::factory()->for($negocio ?? Negocio::factory()->conPlanContratado()->conSucursalPrincipal())->admin()->create();
}

/** @return array<string, mixed> */
function grupoValido(array $cambios = []): array
{
    return ['nombre' => 'Salsas', 'tipo_seleccion' => 'cantidad', 'minimo' => 0, 'maximo' => 4, 'cantidad_gratis' => 2, 'precio_extra' => 300,
        'modificadores' => [
            ['nombre' => 'Mayonesa', 'precio_adicional' => 0, 'cantidad_maxima' => 2, 'activo' => true],
            ['nombre' => 'Ají pebre', 'precio_adicional' => 200, 'cantidad_maxima' => null, 'activo' => true],
        ], ...$cambios];
}

test('modifier endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([
    ['getJson', '/api/v1/grupos-modificadores'], ['postJson', '/api/v1/grupos-modificadores'],
    ['getJson', '/api/v1/grupos-modificadores/01m5n22fn4rjjt0zc1mwgd9faa'], ['putJson', '/api/v1/grupos-modificadores/01m5n22fn4rjjt0zc1mwgd9faa'],
    ['patchJson', '/api/v1/grupos-modificadores/01m5n22fn4rjjt0zc1mwgd9faa/estado'],
]);

test('users without catalog permission receive 403', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();
    $grupo = GrupoModificador::factory()->for($usuario->negocio)->create();
    $this->actingAs($usuario)->getJson('/api/v1/grupos-modificadores')->assertForbidden();
    $this->postJson('/api/v1/grupos-modificadores', grupoValido())->assertForbidden();
    $this->putJson('/api/v1/grupos-modificadores/'.$grupo->public_id, grupoValido())->assertForbidden();
    $this->patchJson('/api/v1/grupos-modificadores/'.$grupo->public_id.'/estado', ['activo' => false])->assertForbidden();
    expect(GrupoModificador::count())->toBe(1)->and($grupo->fresh()->activo)->toBeTrue();
})->with(['cajero', 'cocina', 'garzon']);

test('the routes are closed when the modifiers module is off or not in the plan', function (Negocio $negocio) {
    $admin = adminModificadores($negocio);
    $this->actingAs($admin)->getJson('/api/v1/grupos-modificadores')->assertNotFound();
    $this->postJson('/api/v1/grupos-modificadores', grupoValido())->assertNotFound();
    $this->getJson('/api/v1/productos')->assertOk();
    expect(GrupoModificador::count())->toBe(0);
})->with([
    'module switched off' => fn () => Negocio::factory()->conPlanContratado()->conSucursalPrincipal()->conModulos([Modulo::Comanda])->create(),
    'plan without the module' => fn () => Negocio::factory()->plan(Plan::Retail)->conPlanContratado()->conSucursalPrincipal()->create(),
]);

test('a group is created with free and priced modifiers and normalized fields', function () {
    $admin = adminModificadores();
    $ajeno = Negocio::factory()->create();
    $respuesta = $this->actingAs($admin)->postJson('/api/v1/grupos-modificadores', grupoValido([
        'nombre' => '  Salsas  ', 'referencia' => ' ', 'negocio_id' => $ajeno->id, 'activo' => false,
    ]))->assertCreated()->assertJsonPath('data.nombre', 'Salsas')->assertJsonPath('data.referencia', null)->assertJsonPath('data.activo', true)
        ->assertJsonPath('data.tipo_seleccion', 'cantidad')->assertJsonPath('data.cantidad_gratis', 2)->assertJsonPath('data.precio_extra', 300)
        ->assertJsonCount(2, 'data.modificadores')->assertJsonPath('data.modificadores.0.nombre', 'Mayonesa')->assertJsonPath('data.modificadores.0.precio_adicional', 0)
        ->assertJsonPath('data.modificadores.0.orden', 0)->assertJsonPath('data.modificadores.1.precio_adicional', 200)->assertJsonPath('data.modificadores.1.orden', 1);
    expect(json_encode($respuesta->json()))->not->toContain('negocio_id');
    expect(GrupoModificador::sole()->negocio_id)->toBe($admin->negocio_id)->and(Modificador::count())->toBe(2);
});

test('a single selection group is normalized to one choice without extra charges', function () {
    $admin = adminModificadores();
    $this->actingAs($admin)->postJson('/api/v1/grupos-modificadores', grupoValido([
        'nombre' => 'Tipo de pan', 'tipo_seleccion' => 'unica', 'minimo' => 1, 'maximo' => null, 'cantidad_gratis' => 3, 'precio_extra' => 500,
        'modificadores' => [['nombre' => 'Amasado', 'precio_adicional' => 0, 'cantidad_maxima' => 3, 'activo' => true], ['nombre' => 'Integral', 'precio_adicional' => 300]],
    ]))->assertCreated()->assertJsonPath('data.maximo', 1)->assertJsonPath('data.minimo', 1)->assertJsonPath('data.cantidad_gratis', null)
        ->assertJsonPath('data.precio_extra', 0)->assertJsonPath('data.modificadores.0.cantidad_maxima', null)->assertJsonPath('data.modificadores.1.activo', true);
});

test('invalid group data returns 422', function (array $cambios, string $campo) {
    $admin = adminModificadores();
    $this->actingAs($admin)->postJson('/api/v1/grupos-modificadores', grupoValido($cambios))->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(GrupoModificador::count())->toBe(0);
})->with([
    'no name' => [['nombre' => ' '], 'nombre'], 'long name' => [['nombre' => str_repeat('x', 101)], 'nombre'],
    'array name' => [['nombre' => ['x']], 'nombre'], 'long reference' => [['referencia' => str_repeat('x', 101)], 'referencia'],
    'unknown type' => [['tipo_seleccion' => 'otro'], 'tipo_seleccion'], 'no type' => [['tipo_seleccion' => null], 'tipo_seleccion'],
    'minimum above maximum' => [['minimo' => 5, 'maximo' => 4], 'maximo'], 'negative minimum' => [['minimo' => -1], 'minimo'],
    'single with minimum two' => [['tipo_seleccion' => 'unica', 'minimo' => 2, 'maximo' => null], 'minimo'],
    'single with maximum two' => [['tipo_seleccion' => 'unica', 'minimo' => 0, 'maximo' => 2], 'maximo'],
    'zero maximum' => [['maximo' => 0], 'maximo'], 'huge maximum' => [['maximo' => 51], 'maximo'],
    'decimal extra price' => [['precio_extra' => 10.5], 'precio_extra'], 'negative extra price' => [['precio_extra' => -1], 'precio_extra'],
    'huge extra price' => [['precio_extra' => 10_000_000], 'precio_extra'], 'negative free units' => [['cantidad_gratis' => -1], 'cantidad_gratis'],
    'no modifiers' => [['modificadores' => []], 'modificadores'], 'missing modifiers' => [['modificadores' => null], 'modificadores'],
    'too many modifiers' => [['modificadores' => array_fill(0, 51, ['nombre' => 'x', 'precio_adicional' => 0, 'activo' => true])], 'modificadores'],
    'modifier without name' => [['modificadores' => [['nombre' => ' ', 'precio_adicional' => 0]]], 'modificadores.0.nombre'],
    'decimal modifier price' => [['modificadores' => [['nombre' => 'A', 'precio_adicional' => 1.5]]], 'modificadores.0.precio_adicional'],
    'negative modifier price' => [['modificadores' => [['nombre' => 'A', 'precio_adicional' => -5]]], 'modificadores.0.precio_adicional'],
    'huge modifier price' => [['modificadores' => [['nombre' => 'A', 'precio_adicional' => 10_000_000]]], 'modificadores.0.precio_adicional'],
    'zero modifier maximum' => [['modificadores' => [['nombre' => 'A', 'precio_adicional' => 0, 'cantidad_maxima' => 0]]], 'modificadores.0.cantidad_maxima'],
    'repeated modifier' => [['modificadores' => [['nombre' => 'Ají', 'precio_adicional' => 0], ['nombre' => 'ají', 'precio_adicional' => 0]]], 'modificadores.1.nombre'],
    'all modifiers inactive' => [['modificadores' => [['nombre' => 'A', 'precio_adicional' => 0, 'activo' => false]]], 'modificadores'],
    'minimum above active options' => [['tipo_seleccion' => 'multiple', 'minimo' => 3, 'maximo' => null], 'minimo'],
    'foreign modifier id' => [['modificadores' => [['id' => 'no-es-ulid', 'nombre' => 'A', 'precio_adicional' => 0]]], 'modificadores.0.id'],
]);

test('group names are unique per business without case but reusable across businesses', function () {
    $admin = adminModificadores();
    GrupoModificador::factory()->for($admin->negocio)->create(['nombre' => 'Salsas']);
    GrupoModificador::factory()->create(['nombre' => 'Agregados']);
    $this->actingAs($admin)->postJson('/api/v1/grupos-modificadores', grupoValido(['nombre' => 'SALSAS']))->assertUnprocessable()->assertJsonValidationErrors('nombre');
    $this->postJson('/api/v1/grupos-modificadores', grupoValido(['nombre' => 'Agregados']))->assertCreated();
});

test('editing a group updates modifiers, keeps order and deactivates the ones left out', function () {
    $admin = adminModificadores();
    $grupo = GrupoModificador::factory()->for($admin->negocio)->create(['nombre' => 'Salsas']);
    $mayonesa = Modificador::factory()->for($grupo, 'grupo')->create(['nombre' => 'Mayonesa', 'orden' => 0]);
    $ketchup = Modificador::factory()->for($grupo, 'grupo')->create(['nombre' => 'Ketchup', 'orden' => 1]);
    $ruta = '/api/v1/grupos-modificadores/'.$grupo->public_id;
    $this->actingAs($admin)->putJson($ruta, grupoValido([
        'nombre' => 'Salsas del local', 'modificadores' => [
            ['nombre' => 'Mostaza', 'precio_adicional' => 150],
            ['id' => $mayonesa->public_id, 'nombre' => 'Mayo casera', 'precio_adicional' => 100, 'cantidad_maxima' => 3, 'activo' => true],
        ],
    ]))->assertOk()->assertJsonPath('data.nombre', 'Salsas del local')->assertJsonCount(3, 'data.modificadores')
        ->assertJsonPath('data.modificadores.0.nombre', 'Mostaza')->assertJsonPath('data.modificadores.1.id', $mayonesa->public_id)
        ->assertJsonPath('data.modificadores.1.nombre', 'Mayo casera')->assertJsonPath('data.modificadores.1.precio_adicional', 100)
        ->assertJsonPath('data.modificadores.2.id', $ketchup->public_id)->assertJsonPath('data.modificadores.2.activo', false);
    expect(Modificador::count())->toBe(3)->and($ketchup->fresh()->activo)->toBeFalse();
});

test('a modifier from another group cannot be edited through a group', function () {
    $admin = adminModificadores();
    $grupo = GrupoModificador::factory()->for($admin->negocio)->create();
    $ajeno = Modificador::factory()->create(['nombre' => 'Ajeno']);
    $this->actingAs($admin)->putJson('/api/v1/grupos-modificadores/'.$grupo->public_id, grupoValido([
        'modificadores' => [['id' => $ajeno->public_id, 'nombre' => 'Robado', 'precio_adicional' => 0]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('modificadores.0.id');
    expect($ajeno->fresh()->nombre)->toBe('Ajeno');
});

test('groups from another business are not found', function () {
    $admin = adminModificadores();
    $ajeno = GrupoModificador::factory()->create(['nombre' => 'Ajeno']);
    $ruta = '/api/v1/grupos-modificadores/'.$ajeno->public_id;
    $this->actingAs($admin)->getJson($ruta)->assertNotFound();
    $this->putJson($ruta, grupoValido())->assertNotFound();
    $this->patchJson($ruta.'/estado', ['activo' => false])->assertNotFound();
    expect($ajeno->fresh()->activo)->toBeTrue();
});

test('groups are listed, searched, filtered by state and counted', function () {
    $admin = adminModificadores();
    $salsas = GrupoModificador::factory()->for($admin->negocio)->create(['nombre' => 'Salsas', 'referencia' => 'SAL-1']);
    Modificador::factory()->for($salsas, 'grupo')->count(2)->create();
    GrupoModificador::factory()->for($admin->negocio)->inactivo()->create(['nombre' => 'Panes']);
    GrupoModificador::factory()->create(['nombre' => 'De otro negocio']);
    Producto::factory()->for($admin->negocio)->create()->gruposModificadores()->attach($salsas, ['orden' => 0]);
    $this->actingAs($admin);
    $this->getJson('/api/v1/grupos-modificadores')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.nombre', 'Panes')
        ->assertJsonPath('data.1.productos_count', 1)->assertJsonCount(2, 'data.1.modificadores');
    $this->getJson('/api/v1/grupos-modificadores?buscar=SAL-1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/grupos-modificadores?estado=inactivo')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Panes');
    $this->getJson('/api/v1/grupos-modificadores?estado=otro')->assertUnprocessable();
});

test('a group is deactivated and reactivated without being deleted', function () {
    $admin = adminModificadores();
    $grupo = GrupoModificador::factory()->for($admin->negocio)->create();
    $ruta = '/api/v1/grupos-modificadores/'.$grupo->public_id;
    $this->actingAs($admin)->patchJson($ruta.'/estado', ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);
    $this->patchJson($ruta.'/estado', ['activo' => true])->assertOk()->assertJsonPath('data.activo', true);
    $this->patchJson($ruta.'/estado', ['activo' => 'quizá'])->assertUnprocessable()->assertJsonValidationErrors('activo');
    $this->deleteJson($ruta)->assertStatus(405);
});

function productoParaModificadores(User $admin, array $cambios = []): array
{
    return ['nombre' => 'Completo italiano', 'unidad_venta' => 'unidad', 'precio' => 3500, 'se_vende' => true, 'es_insumo' => false,
        'controla_stock' => false, ...$cambios];
}

test('modifier groups are assigned to a product in the order sent and can be replaced or removed', function () {
    $admin = adminModificadores();
    [$pan, $salsas, $agregados] = collect(['Tipo de pan', 'Salsas', 'Agregados'])
        ->map(fn (string $nombre) => GrupoModificador::factory()->for($admin->negocio)->create(['nombre' => $nombre]))->all();
    $this->actingAs($admin);
    $id = $this->postJson('/api/v1/productos', productoParaModificadores($admin, ['grupos_modificadores' => [$salsas->public_id, $pan->public_id]]))
        ->assertCreated()->assertJsonCount(2, 'data.grupos_modificadores')->assertJsonPath('data.grupos_modificadores.0.nombre', 'Salsas')
        ->assertJsonPath('data.grupos_modificadores.1.nombre', 'Tipo de pan')->json('data.id');
    $ruta = '/api/v1/productos/'.$id;
    $this->putJson($ruta, productoParaModificadores($admin, ['grupos_modificadores' => [$pan->public_id, $agregados->public_id, $salsas->public_id]]))
        ->assertOk()->assertJsonPath('data.grupos_modificadores.0.nombre', 'Tipo de pan')->assertJsonPath('data.grupos_modificadores.2.nombre', 'Salsas');
    $this->getJson($ruta)->assertOk()->assertJsonCount(3, 'data.grupos_modificadores');
    $this->putJson($ruta, productoParaModificadores($admin))->assertOk()->assertJsonCount(3, 'data.grupos_modificadores');
    $this->putJson($ruta, productoParaModificadores($admin, ['grupos_modificadores' => []]))->assertOk()->assertJsonCount(0, 'data.grupos_modificadores');
    expect(GrupoModificador::count())->toBe(3);
});

test('unknown, foreign, inactive, repeated or too many groups are rejected', function () {
    $admin = adminModificadores();
    $propio = GrupoModificador::factory()->for($admin->negocio)->create();
    $inactivo = GrupoModificador::factory()->for($admin->negocio)->inactivo()->create();
    $ajeno = GrupoModificador::factory()->create();
    $this->actingAs($admin);
    foreach ([[$ajeno->public_id], [$inactivo->public_id], ['01m5n22fn4rjjt0zc1mwgd9faa']] as $grupos) {
        $this->postJson('/api/v1/productos', productoParaModificadores($admin, ['grupos_modificadores' => $grupos]))->assertUnprocessable()->assertJsonValidationErrors('grupos_modificadores');
    }
    $this->postJson('/api/v1/productos', productoParaModificadores($admin, ['grupos_modificadores' => [$propio->public_id, $propio->public_id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('grupos_modificadores.1');
    $this->postJson('/api/v1/productos', productoParaModificadores($admin, ['grupos_modificadores' => array_fill(0, 21, $propio->public_id)]))->assertUnprocessable();
    $this->postJson('/api/v1/productos', productoParaModificadores($admin, ['grupos_modificadores' => 'x']))->assertUnprocessable()->assertJsonValidationErrors('grupos_modificadores');
    expect(Producto::count())->toBe(0);
});

test('an inactive group already assigned is kept but cannot be added to other products', function () {
    $admin = adminModificadores();
    $grupo = GrupoModificador::factory()->for($admin->negocio)->create();
    $producto = Producto::factory()->for($admin->negocio)->create();
    $otro = Producto::factory()->for($admin->negocio)->create();
    $producto->gruposModificadores()->attach($grupo, ['orden' => 0]);
    $grupo->update(['activo' => false]);
    $this->actingAs($admin);
    $this->putJson('/api/v1/productos/'.$producto->public_id, productoParaModificadores($admin, ['grupos_modificadores' => [$grupo->public_id]]))
        ->assertOk()->assertJsonPath('data.grupos_modificadores.0.activo', false);
    $this->putJson('/api/v1/productos/'.$otro->public_id, productoParaModificadores($admin, ['grupos_modificadores' => [$grupo->public_id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('grupos_modificadores');
});

test('with the module off the assignments are ignored and kept', function () {
    $negocio = Negocio::factory()->conPlanContratado()->conSucursalPrincipal()->create();
    $admin = adminModificadores($negocio);
    $grupo = GrupoModificador::factory()->for($negocio)->create();
    $producto = Producto::factory()->for($negocio)->create();
    $producto->gruposModificadores()->attach($grupo, ['orden' => 0]);
    $negocio->update(['modulos' => [Modulo::Comanda]]);
    $this->actingAs($admin)->putJson('/api/v1/productos/'.$producto->public_id, productoParaModificadores($admin, ['grupos_modificadores' => []]))->assertOk();
    expect($producto->gruposModificadores()->count())->toBe(1);
    $this->getJson('/api/v1/productos/opciones')->assertOk()->assertJsonPath('grupos_modificadores', []);
});

test('the product options list the modifier groups of the business', function () {
    $admin = adminModificadores();
    GrupoModificador::factory()->for($admin->negocio)->create(['nombre' => 'Salsas', 'tipo_seleccion' => 'cantidad']);
    GrupoModificador::factory()->create(['nombre' => 'Ajeno']);
    $this->actingAs($admin)->getJson('/api/v1/productos/opciones')->assertOk()->assertJsonCount(1, 'grupos_modificadores')
        ->assertJsonPath('grupos_modificadores.0.nombre', 'Salsas')->assertJsonPath('grupos_modificadores.0.tipo_seleccion', 'cantidad');
});
