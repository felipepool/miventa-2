<?php

use App\Models\Categoria;
use App\Models\MovimientoStock;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminInventario(): User
{
    return User::factory()->for(Negocio::factory()->conPlanContratado()->conSucursalPrincipal())->admin()->create();
}

function sucursalPrincipalDe(User $admin): Sucursal
{
    return $admin->negocio->sucursales()->where('es_principal', true)->sole();
}

function productoConStock(User $admin, string $cantidad = '10.000', array $producto = [], array $stock = []): Producto
{
    $registro = Producto::factory()->for($admin->negocio)->create($producto);
    Stock::factory()->create(['producto_id' => $registro->id, 'negocio_id' => $admin->negocio_id, 'sucursal_id' => sucursalPrincipalDe($admin)->id, 'cantidad' => $cantidad, ...$stock]);

    return $registro;
}

test('inventory endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([
    ['getJson', '/api/v1/inventario'], ['getJson', '/api/v1/inventario/01m5n22fn4rjjt0zc1mwgd9faa/movimientos'],
    ['postJson', '/api/v1/inventario/01m5n22fn4rjjt0zc1mwgd9faa/movimientos'], ['putJson', '/api/v1/inventario/01m5n22fn4rjjt0zc1mwgd9faa/minimo'],
]);

test('users without inventory permission receive 403', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();
    $producto = Producto::factory()->for($usuario->negocio)->create();
    $this->actingAs($usuario)->getJson('/api/v1/inventario')->assertForbidden();
    $this->getJson('/api/v1/inventario/'.$producto->public_id.'/movimientos')->assertForbidden();
    $this->postJson('/api/v1/inventario/'.$producto->public_id.'/movimientos', ['tipo' => 'entrada', 'cantidad' => 5])->assertForbidden();
    $this->putJson('/api/v1/inventario/'.$producto->public_id.'/minimo', ['stock_minimo' => 5])->assertForbidden();
    expect(MovimientoStock::count())->toBe(0)->and(Stock::count())->toBe(0);
})->with(['cajero', 'cocina', 'garzon']);

test('inventory requires a confirmed branch when several are available', function () {
    $admin = adminInventario();
    Sucursal::factory()->for($admin->negocio)->create();
    $this->actingAs($admin)->getJson('/api/v1/inventario')->assertStatus(409)->assertJsonPath('code', 'sucursal_requerida');
});

test('the inventory lists only stock controlled products with their stock in the current branch', function () {
    $admin = adminInventario();
    productoConStock($admin, '4.500', ['nombre' => 'Palta', 'unidad_venta' => 'kg'], ['stock_minimo' => '5.000']);
    Producto::factory()->for($admin->negocio)->create(['nombre' => 'Sin movimientos']);
    Producto::factory()->for($admin->negocio)->create(['nombre' => 'Sin control', 'controla_stock' => false]);
    Producto::factory()->create(['nombre' => 'De otro negocio']);

    $respuesta = $this->actingAs($admin)->getJson('/api/v1/inventario')->assertOk()->assertJsonCount(2, 'data');
    $respuesta->assertJsonPath('data.0.nombre', 'Palta')->assertJsonPath('data.0.cantidad', '4.500')
        ->assertJsonPath('data.0.stock_minimo', '5.000')->assertJsonPath('data.0.stock_bajo', true)->assertJsonPath('data.0.unidad_venta', 'kg')
        ->assertJsonPath('data.1.nombre', 'Sin movimientos')->assertJsonPath('data.1.cantidad', '0.000')->assertJsonPath('data.1.stock_bajo', false);
    expect(json_encode($respuesta->json()))->not->toContain('negocio_id')->not->toContain('sucursal_id');
});

test('the inventory can be filtered by category and lists the business categories', function () {
    $admin = adminInventario();
    $bebidas = Categoria::factory()->for($admin->negocio)->create(['nombre' => 'Bebidas']);
    Categoria::factory()->create(['nombre' => 'De otro negocio']);
    productoConStock($admin, '1.000', ['nombre' => 'Cola', 'categoria_id' => $bebidas->id]);
    productoConStock($admin, '1.000', ['nombre' => 'Pan']);
    $this->actingAs($admin)->getJson('/api/v1/inventario?categoria='.$bebidas->public_id)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Cola')->assertJsonFragment(['nombre' => 'Bebidas'])->assertJsonMissing(['nombre' => 'De otro negocio']);
});

test('the inventory can be searched and filtered by low stock', function () {
    $admin = adminInventario();
    productoConStock($admin, '2.000', ['nombre' => 'Pan', 'sku' => 'PAN-1'], ['stock_minimo' => '3.000']);
    productoConStock($admin, '9.000', ['nombre' => 'Vienesa'], ['stock_minimo' => '3.000']);
    productoConStock($admin, '0.000', ['nombre' => 'Tomate']);
    $this->actingAs($admin);
    $this->getJson('/api/v1/inventario?buscar=PAN-1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Pan');
    $this->getJson('/api/v1/inventario?stock=bajo')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Pan');
    $this->getJson('/api/v1/inventario?stock=otro')->assertUnprocessable()->assertJsonValidationErrors('stock');
});

test('stock is kept separately for each branch', function () {
    $admin = adminInventario();
    $otra = Sucursal::factory()->for($admin->negocio)->create();
    $producto = productoConStock($admin, '10.000');
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$otra->public_id)->assertOk();
    $this->getJson('/api/v1/inventario')->assertOk()->assertJsonPath('data.0.cantidad', '0.000');
    $this->postJson('/api/v1/inventario/'.$producto->public_id.'/movimientos', ['tipo' => 'entrada', 'cantidad' => 3])->assertCreated();
    $this->getJson('/api/v1/inventario')->assertOk()->assertJsonPath('data.0.cantidad', '3.000');
    expect(Stock::withoutGlobalScopes()->where('producto_id', $producto->id)->count())->toBe(2)
        ->and(Stock::withoutGlobalScopes()->where('sucursal_id', sucursalPrincipalDe($admin)->id)->sole()->cantidad)->toBe('10.000');
});

test('entries, exits and adjustments update the stock and the history', function () {
    $admin = adminInventario();
    $producto = productoConStock($admin, '10.000', ['unidad_venta' => 'kg']);
    $ruta = '/api/v1/inventario/'.$producto->public_id.'/movimientos';
    $this->actingAs($admin);
    $this->postJson($ruta, ['tipo' => 'entrada', 'cantidad' => '2.5', 'motivo' => '  Compra  '])->assertCreated()
        ->assertJsonPath('data.tipo', 'entrada')->assertJsonPath('data.cantidad', '2.500')->assertJsonPath('data.stock_resultante', '12.500')
        ->assertJsonPath('data.motivo', 'Compra')->assertJsonPath('data.usuario', $admin->name);
    $this->postJson($ruta, ['tipo' => 'salida', 'cantidad' => 4, 'motivo' => 'Merma'])->assertCreated()
        ->assertJsonPath('data.cantidad', '-4.000')->assertJsonPath('data.stock_resultante', '8.500');
    $this->postJson($ruta, ['tipo' => 'ajuste', 'cantidad' => '7.250', 'motivo' => 'Conteo'])->assertCreated()
        ->assertJsonPath('data.cantidad', '-1.250')->assertJsonPath('data.stock_resultante', '7.250');
    $this->getJson('/api/v1/inventario')->assertJsonPath('data.0.cantidad', '7.250');
    $this->getJson('/api/v1/inventario/'.$producto->public_id.'/movimientos')->assertOk()->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.tipo', 'ajuste')->assertJsonPath('data.2.tipo', 'entrada');
    expect(MovimientoStock::count())->toBe(3);
});

test('invalid movements are rejected without touching the stock', function (array $datos, string $campo) {
    $admin = adminInventario();
    $producto = productoConStock($admin, '10.000');
    $this->actingAs($admin)->postJson('/api/v1/inventario/'.$producto->public_id.'/movimientos', $datos)->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(MovimientoStock::count())->toBe(0)->and(Stock::sole()->cantidad)->toBe('10.000');
})->with([
    'no type' => [['cantidad' => 1], 'tipo'], 'unknown type' => [['tipo' => 'venta', 'cantidad' => 1], 'tipo'],
    'no quantity' => [['tipo' => 'entrada'], 'cantidad'], 'zero entry' => [['tipo' => 'entrada', 'cantidad' => 0], 'cantidad'],
    'negative exit' => [['tipo' => 'salida', 'cantidad' => -1, 'motivo' => 'x'], 'cantidad'], 'text quantity' => [['tipo' => 'entrada', 'cantidad' => 'mucho'], 'cantidad'],
    'array quantity' => [['tipo' => 'entrada', 'cantidad' => [1]], 'cantidad'], 'decimals on units' => [['tipo' => 'entrada', 'cantidad' => 1.5], 'cantidad'],
    'huge quantity' => [['tipo' => 'entrada', 'cantidad' => 100_000_000], 'cantidad'], 'negative adjustment' => [['tipo' => 'ajuste', 'cantidad' => -1, 'motivo' => 'x'], 'cantidad'],
    'exit without reason' => [['tipo' => 'salida', 'cantidad' => 1], 'motivo'], 'adjustment without reason' => [['tipo' => 'ajuste', 'cantidad' => 1, 'motivo' => ' '], 'motivo'],
    'long reason' => [['tipo' => 'entrada', 'cantidad' => 1, 'motivo' => str_repeat('x', 201)], 'motivo'],
    'array reason' => [['tipo' => 'entrada', 'cantidad' => 1, 'motivo' => ['x']], 'motivo'],
]);

test('measured products accept up to three decimals and no more', function () {
    $admin = adminInventario();
    $producto = productoConStock($admin, '0.000', ['unidad_venta' => 'm']);
    $ruta = '/api/v1/inventario/'.$producto->public_id.'/movimientos';
    $this->actingAs($admin)->postJson($ruta, ['tipo' => 'entrada', 'cantidad' => '1.0005'])->assertUnprocessable()->assertJsonValidationErrors('cantidad');
    $this->postJson($ruta, ['tipo' => 'entrada', 'cantidad' => '1.005'])->assertCreated()->assertJsonPath('data.stock_resultante', '1.005');
});

test('an exit larger than the available stock is rejected', function () {
    $admin = adminInventario();
    $producto = productoConStock($admin, '3.000');
    $this->actingAs($admin)->postJson('/api/v1/inventario/'.$producto->public_id.'/movimientos', ['tipo' => 'salida', 'cantidad' => 4, 'motivo' => 'Merma'])
        ->assertUnprocessable()->assertJsonValidationErrors('cantidad');
    expect(MovimientoStock::count())->toBe(0)->and(Stock::sole()->cantidad)->toBe('3.000');
    $this->postJson('/api/v1/inventario/'.$producto->public_id.'/movimientos', ['tipo' => 'salida', 'cantidad' => 3, 'motivo' => 'Merma'])->assertCreated()->assertJsonPath('data.stock_resultante', '0.000');
});

test('the first movement creates the stock record and the client cannot set protected fields', function () {
    $admin = adminInventario();
    $ajeno = Negocio::factory()->conSucursalPrincipal()->create();
    $producto = Producto::factory()->for($admin->negocio)->create();
    $this->actingAs($admin)->postJson('/api/v1/inventario/'.$producto->public_id.'/movimientos', [
        'tipo' => 'entrada', 'cantidad' => 5, 'negocio_id' => $ajeno->id, 'sucursal_id' => 999, 'user_id' => 999, 'stock_resultante' => 999,
    ])->assertCreated()->assertJsonPath('data.stock_resultante', '5.000');
    $movimiento = MovimientoStock::sole();
    expect($movimiento->negocio_id)->toBe($admin->negocio_id)->and($movimiento->sucursal_id)->toBe(sucursalPrincipalDe($admin)->id)->and($movimiento->user_id)->toBe($admin->id);
});

test('products from another business or without stock control cannot be moved', function () {
    $admin = adminInventario();
    $ajeno = Producto::factory()->create();
    $sinControl = Producto::factory()->for($admin->negocio)->create(['controla_stock' => false]);
    $this->actingAs($admin);
    $this->postJson('/api/v1/inventario/'.$ajeno->public_id.'/movimientos', ['tipo' => 'entrada', 'cantidad' => 1])->assertNotFound();
    $this->getJson('/api/v1/inventario/'.$ajeno->public_id.'/movimientos')->assertNotFound();
    $this->putJson('/api/v1/inventario/'.$ajeno->public_id.'/minimo', ['stock_minimo' => 1])->assertNotFound();
    $this->postJson('/api/v1/inventario/'.$sinControl->public_id.'/movimientos', ['tipo' => 'entrada', 'cantidad' => 1])->assertUnprocessable()->assertJsonValidationErrors('producto');
    expect(MovimientoStock::count())->toBe(0)->and(Stock::count())->toBe(0);
});

test('the history only shows movements of the current branch', function () {
    $admin = adminInventario();
    $otra = Sucursal::factory()->for($admin->negocio)->create();
    $producto = productoConStock($admin);
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.sucursalPrincipalDe($admin)->public_id)->assertOk();
    $this->postJson('/api/v1/inventario/'.$producto->public_id.'/movimientos', ['tipo' => 'entrada', 'cantidad' => 1])->assertCreated();
    $this->putJson('/api/v1/sucursal-actual/'.$otra->public_id)->assertOk();
    $this->getJson('/api/v1/inventario/'.$producto->public_id.'/movimientos')->assertOk()->assertJsonCount(0, 'data');
});

test('the minimum stock is set per branch and drives the low stock flag', function () {
    $admin = adminInventario();
    $producto = productoConStock($admin, '2.000');
    $ruta = '/api/v1/inventario/'.$producto->public_id.'/minimo';
    $this->actingAs($admin);
    $this->putJson($ruta, ['stock_minimo' => 5])->assertOk()->assertJsonPath('data.stock_minimo', '5.000')->assertJsonPath('data.stock_bajo', true)->assertJsonPath('data.cantidad', '2.000');
    $this->putJson($ruta, ['stock_minimo' => 0])->assertOk()->assertJsonPath('data.stock_bajo', false);
    foreach ([null, -1, 'x', 1.5, 100_000_000, [1]] as $invalido) {
        $this->putJson($ruta, ['stock_minimo' => $invalido])->assertUnprocessable()->assertJsonValidationErrors('stock_minimo');
    }
    expect(Stock::sole()->stock_minimo)->toBe('0.000');
});

test('movements are definitive records', function () {
    $movimiento = MovimientoStock::factory()->create();
    $movimiento->motivo = 'Cambiado';
    expect(fn () => $movimiento->save())->toThrow(LogicException::class)
        ->and(fn () => $movimiento->delete())->toThrow(LogicException::class);
});
