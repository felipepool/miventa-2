<?php

use App\Enums\ConceptoTarifa;
use App\Models\Caja;
use App\Models\Devolucion;
use App\Models\MovimientoStock;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function negocioQueCorrige(bool $conInventario = false): Negocio
{
    $fabrica = Negocio::factory()->conPlanContratado();

    return ($conInventario ? $fabrica->conAdicional(ConceptoTarifa::Inventario) : $fabrica)->conSucursalPrincipal()->create(['cierre_ciego' => false]);
}

/** Usuario con su turno abierto en la caja principal, o en una caja nueva si esa ya está ocupada. */
function usuarioConCaja(Negocio $negocio, string $rol = 'admin', int $montoInicial = 10000): User
{
    $usuario = User::factory()->for($negocio)->{$rol}()->create();
    $ocupadas = TurnoCaja::withoutGlobalScopes()->whereNull('cerrada_at')->pluck('caja_id');
    $caja = Caja::where('negocio_id', $negocio->id)->whereNotIn('id', $ocupadas)->orderBy('id')->first()
        ?? Caja::factory()->create(['negocio_id' => $negocio->id, 'sucursal_id' => $negocio->sucursales()->sole()->id]);
    TurnoCaja::factory()->create(['caja_id' => $caja->id, 'user_id' => $usuario->id, 'monto_inicial' => $montoInicial,
        'numero' => (int) TurnoCaja::withoutGlobalScopes()->where('caja_id', $caja->id)->max('numero') + 1]);

    return $usuario;
}

function productoParaCorregir(Negocio $negocio, int $precio = 1000, array $cambios = []): Producto
{
    return Producto::factory()->for($negocio)->create(['precio' => $precio, 'se_vende' => true, 'controla_stock' => false, ...$cambios]);
}

/** Vende por la API y devuelve la venta registrada. */
function vender(User $vendedor, array $items, array $pagos, array $extra = []): Venta
{
    $id = test()->actingAs($vendedor)->postJson('/api/v1/ventas', ['items' => $items, 'pagos' => $pagos, ...$extra])->assertCreated()->json('data.id');

    return Venta::withoutGlobalScopes()->where('public_id', $id)->sole();
}

function devolucionDe(array $items, string $medio = 'efectivo', array $cambios = []): array
{
    return ['items' => $items, 'medio' => $medio, 'motivo' => 'Cliente arrepentido', 'reintegrar_stock' => true, ...$cambios];
}

test('cancel, refund and receipt link endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([
    ['postJson', '/api/v1/ventas/01m5n22fn4rjjt0zc1mwgd9faa/anular'],
    ['postJson', '/api/v1/ventas/01m5n22fn4rjjt0zc1mwgd9faa/devoluciones'],
    ['getJson', '/api/v1/ventas/01m5n22fn4rjjt0zc1mwgd9faa/enlace-comprobante'],
]);

test('cashiers without the cancel permission cannot cancel or refund', function () {
    $negocio = negocioQueCorrige();
    $cajero = usuarioConCaja($negocio, 'cajero');
    $venta = vender($cajero, [['producto' => productoParaCorregir($negocio)->public_id, 'cantidad' => 1]], [['medio' => 'efectivo']]);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/anular", ['motivo' => 'Error'])->assertForbidden();
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]]))->assertForbidden();
    expect($venta->fresh()->estado->value)->toBe('pagada')->and(Devolucion::count())->toBe(0);
});

test('a sale is cancelled while its shift is open and leaves the expected cash', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $venta = vender($admin, [['producto' => productoParaCorregir($negocio, 3000)->public_id, 'cantidad' => 1]], [['medio' => 'efectivo', 'recibido' => 5000]]);
    $this->getJson('/api/v1/caja')->assertJsonPath('data.turno.monto_esperado', 13000);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/anular", ['motivo' => ' '])->assertUnprocessable()->assertJsonValidationErrors(['motivo' => 'Indica el motivo de la anulación.']);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/anular", ['motivo' => 'Cobro duplicado'])->assertOk()
        ->assertJsonPath('data.estado', 'anulada')->assertJsonPath('data.motivo_anulacion', 'Cobro duplicado')->assertJsonPath('data.anulada_por', $admin->name);
    $this->getJson('/api/v1/caja')->assertJsonPath('data.turno.ventas_efectivo', 0)->assertJsonPath('data.turno.monto_esperado', 10000);
    expect($venta->fresh()->anulada_at)->not->toBeNull();
});

test('a sale cannot be cancelled when it cannot be reverted cleanly', function (string $caso, string $mensaje) {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $venta = vender($admin, [['producto' => productoParaCorregir($negocio)->public_id, 'cantidad' => 2]], [['medio' => 'debito', 'monto' => 2000]]);
    match ($caso) {
        'anulada' => $this->postJson("/api/v1/ventas/{$venta->public_id}/anular", ['motivo' => 'Error'])->assertOk(),
        'cerrado' => TurnoCaja::whereKey($venta->turno_caja_id)->update(['cerrada_at' => now()]),
        'devolucion' => $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito'))->assertCreated(),
    };
    $this->postJson("/api/v1/ventas/{$venta->public_id}/anular", ['motivo' => 'Error'])->assertUnprocessable()->assertJsonValidationErrors(['venta' => $mensaje]);
})->with([
    'already cancelled' => ['anulada', 'Esta venta ya está anulada.'],
    'closed shift' => ['cerrado', 'El turno de esta venta ya se cerró. Registra una devolución en vez de anularla.'],
    'with refunds' => ['devolucion', 'Esta venta tiene devoluciones. Registra otra devolución en vez de anularla.'],
]);

test('cancelling returns the sold stock only with the inventory add-on', function (bool $conInventario) {
    $negocio = negocioQueCorrige($conInventario);
    $admin = usuarioConCaja($negocio);
    $producto = productoParaCorregir($negocio, 1000, ['controla_stock' => true, 'unidad_venta' => 'kg']);
    Stock::factory()->create(['producto_id' => $producto->id, 'negocio_id' => $negocio->id, 'sucursal_id' => $negocio->sucursales()->sole()->id, 'cantidad' => '5.000']);
    $venta = vender($admin, [['producto' => $producto->public_id, 'cantidad' => '1.500'], ['producto' => $producto->public_id, 'cantidad' => '0.500']], [['medio' => 'debito', 'monto' => 2000]]);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/anular", ['motivo' => 'Error'])->assertOk();
    $stock = Stock::where('producto_id', $producto->id)->sole()->cantidad;
    if ($conInventario) {
        $reintegro = MovimientoStock::where('tipo', 'anulacion')->sole();
        expect($stock)->toBe('5.000')->and($reintegro->cantidad)->toBe('2.000')->and($reintegro->motivo)->toBe('Anulación de la venta N° 1')->and($reintegro->venta_id)->toBe($venta->id);
    } else {
        expect($stock)->toBe('5.000')->and(MovimientoStock::count())->toBe(0);
    }
})->with(['with add-on' => [true], 'without add-on' => [false]]);

test('refunds are proportional to what each line charged and the last one takes the rest', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $producto = productoParaCorregir($negocio, 1000);
    $venta = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 3]], [['medio' => 'debito', 'monto' => 2333]],
        ['descuento' => ['tipo' => 'monto', 'valor' => 667], 'motivo_descuento' => 'Cliente frecuente']);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito'))->assertCreated()
        ->assertJsonPath('data.devolucion', 'parcial')->assertJsonPath('data.total_devuelto', 778)->assertJsonPath('data.items.0.devuelta', '1.000')
        ->assertJsonPath('data.devoluciones.0.monto', 778)->assertJsonPath('data.devoluciones.0.etiqueta', 'Débito');
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 3]], 'debito'))->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.cantidad' => 'Puedes devolver hasta 2 de '.$producto->nombre.'.']);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 2]], 'debito'))->assertCreated()
        ->assertJsonPath('data.devolucion', 'total')->assertJsonPath('data.total_devuelto', 2333);
    expect(Devolucion::pluck('monto')->all())->toBe([778, 1555]);
});

test('cash refunds are rounded to ten pesos and lower the expected cash of the refunding shift', function () {
    $negocio = negocioQueCorrige();
    $vendedor = usuarioConCaja($negocio, 'cajero');
    $venta = vender($vendedor, [['producto' => productoParaCorregir($negocio, 1004)->public_id, 'cantidad' => 1]], [['medio' => 'credito', 'monto' => 1004]]);
    TurnoCaja::whereKey($venta->turno_caja_id)->update(['cerrada_at' => now()]);
    $admin = usuarioConCaja($negocio, 'admin', 5000);
    $this->actingAs($admin)->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]]))->assertCreated()
        ->assertJsonPath('data.devoluciones.0.monto', 1000)->assertJsonPath('data.devoluciones.0.usuario', $admin->name);
    $this->getJson('/api/v1/caja')->assertJsonPath('data.turno.devoluciones_efectivo', 1000)->assertJsonPath('data.turno.monto_esperado', 4000);
    expect(Devolucion::sole()->turno_caja_id)->toBe(TurnoCaja::where('user_id', $admin->id)->sole()->id);
});

test('card refunds do not change the expected cash', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $venta = vender($admin, [['producto' => productoParaCorregir($negocio, 2000)->public_id, 'cantidad' => 1]], [['medio' => 'efectivo']]);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'transferencia'))->assertCreated();
    $this->getJson('/api/v1/caja')->assertJsonPath('data.turno.devoluciones_efectivo', 0)->assertJsonPath('data.turno.monto_esperado', 12000);
});

test('invalid refunds are rejected', function (Closure $armar, string $campo, string $mensaje) {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio, 'admin', 0);
    $producto = productoParaCorregir($negocio, 1000);
    $venta = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 2]], [['medio' => 'debito', 'monto' => 2000]]);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", $armar($venta))->assertUnprocessable()->assertJsonValidationErrors([$campo => $mensaje]);
    expect(Devolucion::count())->toBe(0);
})->with([
    'no lines' => [fn () => devolucionDe([]), 'items', 'Elige al menos un producto a devolver.'],
    'unknown line' => [fn () => devolucionDe([['linea' => 5, 'cantidad' => 1]], 'debito'), 'items.0.linea', 'Esta línea no es parte de la venta o está repetida.'],
    'repeated line' => [fn () => devolucionDe([['linea' => 0, 'cantidad' => 1], ['linea' => 0, 'cantidad' => 1]], 'debito'), 'items.1.linea', 'Esta línea no es parte de la venta o está repetida.'],
    'decimals on a unit product' => [fn () => devolucionDe([['linea' => 0, 'cantidad' => '1.5']], 'debito'), 'items.0.cantidad', 'Este producto se vende por unidad: usa una cantidad entera.'],
    'zero quantity' => [fn () => devolucionDe([['linea' => 0, 'cantidad' => 0]], 'debito'), 'items.0.cantidad', 'La cantidad debe ser mayor que cero.'],
    'unknown method' => [fn () => devolucionDe([['linea' => 0, 'cantidad' => 1]], 'cheque'), 'medio', 'Elige un medio válido para devolver el dinero.'],
    'no reason' => [fn () => devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito', ['motivo' => '']), 'motivo', 'Indica el motivo de la devolución.'],
    'not enough cash in the drawer' => [fn () => devolucionDe([['linea' => 0, 'cantidad' => 1]]), 'medio', 'No hay suficiente efectivo en tu caja para esta devolución. Disponible: $0.'],
]);

test('a refund needs the open shift of whoever refunds and a sale that is not cancelled', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $venta = vender($admin, [['producto' => productoParaCorregir($negocio)->public_id, 'cantidad' => 1]], [['medio' => 'debito', 'monto' => 1000]]);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/anular", ['motivo' => 'Error'])->assertOk();
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito'))->assertUnprocessable()
        ->assertJsonValidationErrors(['venta' => 'Esta venta está anulada: no tiene nada que devolver.']);
    $otroAdmin = User::factory()->for($negocio)->admin()->create(['username' => 'otro-admin']);
    $this->actingAs($otroAdmin)->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito'))->assertUnprocessable()
        ->assertJsonValidationErrors(['turno' => 'Abre tu caja para registrar una devolución.']);
});

test('refunded products go back to stock only when asked and with the add-on', function (bool $reintegrar) {
    $negocio = negocioQueCorrige(true);
    $admin = usuarioConCaja($negocio);
    $producto = productoParaCorregir($negocio, 1000, ['controla_stock' => true]);
    $libre = productoParaCorregir($negocio, 500);
    Stock::factory()->create(['producto_id' => $producto->id, 'negocio_id' => $negocio->id, 'sucursal_id' => $negocio->sucursales()->sole()->id, 'cantidad' => '10.000']);
    $venta = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 3], ['producto' => $libre->public_id, 'cantidad' => 1]], [['medio' => 'debito', 'monto' => 3500]]);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 2], ['linea' => 1, 'cantidad' => 1]], 'debito', ['reintegrar_stock' => $reintegrar]))
        ->assertCreated()->assertJsonPath('data.devoluciones.0.reintegra_stock', $reintegrar);
    expect(Stock::where('producto_id', $producto->id)->sole()->cantidad)->toBe($reintegrar ? '9.000' : '7.000')
        ->and(MovimientoStock::where('tipo', 'devolucion')->count())->toBe($reintegrar ? 1 : 0)
        ->and(Stock::where('producto_id', $libre->id)->count())->toBe(0);
})->with(['back to stock' => [true], 'damaged' => [false]]);

test('repeating a refund with the same idempotency key does not duplicate it', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $venta = vender($admin, [['producto' => productoParaCorregir($negocio)->public_id, 'cantidad' => 2]], [['medio' => 'debito', 'monto' => 2000]]);
    $pedido = devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito', ['clave_idempotencia' => 'devolucion-1']);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", $pedido)->assertCreated();
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", $pedido)->assertOk()->assertJsonCount(1, 'data.devoluciones');
    expect(Devolucion::count())->toBe(1);
});

test('refunds never exceed the charged balance across payment methods', function (string $medio) {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $venta = vender($admin, [['producto' => productoParaCorregir($negocio, 1006)->public_id, 'cantidad' => 2]], [['medio' => 'debito', 'monto' => 2012]]);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]]))->assertCreated()
        ->assertJsonPath('data.total_devuelto', 1010);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], $medio))->assertCreated()
        ->assertJsonPath('data.total_devuelto', $medio === 'efectivo' ? 2010 : 2012)->assertJsonPath('data.devolucion', 'total');
    expect(Devolucion::orderBy('id')->pluck('monto')->all())->toBe([1010, $medio === 'efectivo' ? 1000 : 1002]);
    expect(Devolucion::with('items')->get()->sum(fn (Devolucion $devolucion) => $devolucion->items->sum('monto')))
        ->toBe($medio === 'efectivo' ? 2010 : 2012);
})->with(['debito', 'credito', 'transferencia', 'efectivo']);

test('a card refund respects the original cash rounding', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $venta = vender($admin, [['producto' => productoParaCorregir($negocio, 1004)->public_id, 'cantidad' => 1]], [['medio' => 'efectivo']]);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito'))->assertCreated()
        ->assertJsonPath('data.total_devuelto', 1000);
    expect(Devolucion::sole()->monto)->toBe(1000);
});

test('a refund key cannot be reused for a different request', function (string $cambio) {
    $negocio = negocioQueCorrige(true);
    $admin = usuarioConCaja($negocio);
    $producto = productoParaCorregir($negocio, 1000, ['controla_stock' => true]);
    Stock::factory()->create(['producto_id' => $producto->id, 'negocio_id' => $negocio->id, 'sucursal_id' => $negocio->sucursales()->sole()->id, 'cantidad' => '10.000']);
    $venta = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 3]], [['medio' => 'debito', 'monto' => 3000]]);
    $otra = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 1]], [['medio' => 'debito', 'monto' => 1000]]);
    $pedido = devolucionDe([['linea' => 0, 'cantidad' => 1]], 'efectivo', ['clave_idempotencia' => 'solicitud-unica']);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", $pedido)->assertCreated();
    $modificado = match ($cambio) {
        'cantidad' => [...$pedido, 'items' => [['linea' => 0, 'cantidad' => 2]]],
        'linea' => [...$pedido, 'items' => [['linea' => 1, 'cantidad' => 1]]],
        'medio' => [...$pedido, 'medio' => 'debito'],
        'motivo' => [...$pedido, 'motivo' => 'Otro motivo'],
        'stock' => [...$pedido, 'reintegrar_stock' => false],
        default => $pedido,
    };
    $destino = $cambio === 'venta' ? $otra : $venta;
    $this->postJson("/api/v1/ventas/{$destino->public_id}/devoluciones", $modificado)->assertConflict()
        ->assertJsonPath('message', 'Esta clave de idempotencia ya se utilizó para otra solicitud.');
    expect(Devolucion::count())->toBe(1)
        ->and(Stock::where('producto_id', $producto->id)->sole()->cantidad)->toBe('7.000')
        ->and(MovimientoStock::where('tipo', 'devolucion')->count())->toBe(1);
    $this->getJson('/api/v1/caja')->assertJsonPath('data.turno.monto_esperado', 9000);
})->with(['venta', 'cantidad', 'linea', 'medio', 'motivo', 'stock']);

test('equivalent refund quantities and reordered lines replay without duplicating stock', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $producto = productoParaCorregir($negocio);
    $venta = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 2], ['producto' => $producto->public_id, 'cantidad' => 1]], [['medio' => 'debito', 'monto' => 3000]]);
    $pedido = devolucionDe([['linea' => 0, 'cantidad' => 1], ['linea' => 1, 'cantidad' => 1]], 'debito', ['clave_idempotencia' => 'mismo-pedido']);
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", $pedido)->assertCreated();
    $pedido['items'] = [['linea' => 1, 'cantidad' => '1.000'], ['linea' => 0, 'cantidad' => '1.0']];
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", $pedido)->assertOk()->assertJsonPath('data.total_devuelto', 2000);
    expect(Devolucion::count())->toBe(1);
});

test('different businesses can use the same refund key independently', function () {
    $negocios = [negocioQueCorrige(), negocioQueCorrige()];
    $escenarios = array_map(fn (Negocio $negocio) => [usuarioConCaja($negocio), productoParaCorregir($negocio)], $negocios);
    foreach ($escenarios as [$admin, $producto]) {
        $venta = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 1]], [['medio' => 'debito', 'monto' => 1000]]);
        $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito', ['clave_idempotencia' => 'misma-clave']))
            ->assertCreated()->assertJsonPath('data.total_devuelto', 1000);
    }
    expect(Devolucion::withoutGlobalScopes()->count())->toBe(2);
});

test('sales of another branch or business cannot be cancelled, refunded or shared', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $otro = negocioQueCorrige();
    $ajena = vender(usuarioConCaja($otro), [['producto' => productoParaCorregir($otro)->public_id, 'cantidad' => 1]], [['medio' => 'debito', 'monto' => 1000]]);
    $this->actingAs($admin);
    $this->postJson("/api/v1/ventas/{$ajena->public_id}/anular", ['motivo' => 'Error'])->assertNotFound();
    $this->postJson("/api/v1/ventas/{$ajena->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito'))->assertNotFound();
    $this->getJson("/api/v1/ventas/{$ajena->public_id}/enlace-comprobante")->assertNotFound();
    expect($ajena->fresh()->estado->value)->toBe('pagada');
});

test('sales cannot be cancelled or refunded when the business is read only', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $venta = vender($admin, [['producto' => productoParaCorregir($negocio)->public_id, 'cantidad' => 1]], [['medio' => 'debito', 'monto' => 1000]]);
    $negocio->suscripciones()->delete();
    $negocio->forceFill(['prueba_desde' => now()->subDays(30), 'prueba_hasta' => now()->subDays(16)])->save();
    $this->postJson("/api/v1/ventas/{$venta->public_id}/anular", ['motivo' => 'Error'])->assertForbidden()->assertJsonPath('code', 'negocio_solo_lectura');
    $this->postJson("/api/v1/ventas/{$venta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito'))->assertForbidden();
    expect($venta->fresh()->estado->value)->toBe('pagada');
});

test('the sales history shows and filters cancelled and refunded sales', function () {
    $negocio = negocioQueCorrige();
    $admin = usuarioConCaja($negocio);
    $producto = productoParaCorregir($negocio);
    $pagada = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 1]], [['medio' => 'debito', 'monto' => 1000]]);
    $anulada = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 1]], [['medio' => 'debito', 'monto' => 1000]]);
    $devuelta = vender($admin, [['producto' => $producto->public_id, 'cantidad' => 2]], [['medio' => 'debito', 'monto' => 2000]]);
    $this->postJson("/api/v1/ventas/{$anulada->public_id}/anular", ['motivo' => 'Error'])->assertOk();
    $this->postJson("/api/v1/ventas/{$devuelta->public_id}/devoluciones", devolucionDe([['linea' => 0, 'cantidad' => 1]], 'debito'))->assertCreated();
    $this->getJson('/api/v1/ventas?estado=pagada')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pagada->public_id)->assertJsonPath('data.0.devolucion', null);
    $this->getJson('/api/v1/ventas?estado=anulada')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $anulada->public_id)->assertJsonPath('data.0.estado', 'anulada');
    $this->getJson('/api/v1/ventas?estado=con_devolucion')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $devuelta->public_id)
        ->assertJsonPath('data.0.devolucion', 'parcial')->assertJsonPath('data.0.total_devuelto', 1000);
    $this->getJson('/api/v1/ventas?estado=borrada')->assertUnprocessable()->assertJsonValidationErrors('estado');
});

test('the receipt link opens the receipt without a session only with a valid signature', function () {
    $negocio = negocioQueCorrige();
    $vendedor = usuarioConCaja($negocio, 'cajero');
    $vendedor->update(['name' => 'María Pérez']);
    $venta = vender($vendedor, [['producto' => productoParaCorregir($negocio, 1500, ['nombre' => 'Café'])->public_id, 'cantidad' => 2]], [['medio' => 'efectivo', 'recibido' => 5000]]);
    $respuesta = $this->getJson("/api/v1/ventas/{$venta->public_id}/enlace-comprobante")->assertOk()
        ->assertJsonPath('data.comprobante.folio', 1)->assertJsonPath('data.comprobante.total', 3000)->assertJsonPath('data.comprobante.pagos.0.vuelto', 2000);
    $enlace = $respuesta->json('data.enlace');
    expect($enlace)->toStartWith(url('/comprobante/'.$venta->public_id).'?');
    $api = '/api/v1/comprobantes/'.$venta->public_id.'?'.parse_url($enlace, PHP_URL_QUERY);
    auth()->forgetGuards();
    $this->app['auth']->guard('web')->logout();
    $this->getJson($api)->assertOk()->assertJsonPath('data.cajero', 'María')->assertJsonPath('data.items.0.nombre', 'Café')
        ->assertJsonPath('data.negocio.nombre', $negocio->nombre)->assertJsonMissingPath('data.id')->assertJsonMissingPath('data.items.0.costo_unitario');
    $this->getJson(str_replace('signature=', 'signature=0', $api))->assertForbidden();
    $this->getJson('/api/v1/comprobantes/'.$venta->public_id)->assertForbidden();
    $this->travel(31)->days();
    $this->getJson($api)->assertForbidden();
});
