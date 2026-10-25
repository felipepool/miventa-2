<?php

use App\Enums\ConceptoTarifa;
use App\Models\Caja;
use App\Models\GrupoModificador;
use App\Models\Modificador;
use App\Models\MovimientoStock;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function negocioDeVentas(array $estado = [], bool $conInventario = false): Negocio
{
    $fabrica = Negocio::factory()->conPlanContratado();

    return ($conInventario ? $fabrica->conAdicional(ConceptoTarifa::Inventario) : $fabrica)->conSucursalPrincipal()->create($estado);
}

/** Usuario con su turno de caja abierto en la sucursal principal. */
function vendedorConTurno(Negocio $negocio, string $rol = 'cajero', int $montoInicial = 10000): User
{
    $usuario = User::factory()->for($negocio)->{$rol}()->create();
    $caja = Caja::where('negocio_id', $negocio->id)->orderBy('id')->firstOrFail();
    TurnoCaja::factory()->create(['caja_id' => $caja->id, 'user_id' => $usuario->id, 'monto_inicial' => $montoInicial, 'numero' => (int) TurnoCaja::withoutGlobalScopes()->where('caja_id', $caja->id)->max('numero') + 1]);

    return $usuario;
}

function productoEnVenta(Negocio $negocio, int $precio = 1500, array $cambios = []): Producto
{
    return Producto::factory()->for($negocio)->create(['precio' => $precio, 'se_vende' => true, 'controla_stock' => false, ...$cambios]);
}

/** @return array<string, mixed> */
function ventaDe(Producto $producto, array $cambios = [], int|string $cantidad = 1): array
{
    return ['items' => [['producto' => $producto->public_id, 'cantidad' => $cantidad]], 'pagos' => [['medio' => 'efectivo', 'recibido' => 5000]], ...$cambios];
}

test('sales endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([['getJson', '/api/v1/pos'], ['getJson', '/api/v1/ventas'], ['postJson', '/api/v1/ventas'], ['getJson', '/api/v1/ventas/01m5n22fn4rjjt0zc1mwgd9faa']]);

test('users without the sell permission receive 403', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();
    $this->actingAs($usuario)->getJson('/api/v1/pos')->assertForbidden();
    $this->getJson('/api/v1/ventas')->assertForbidden();
    $this->postJson('/api/v1/ventas', ventaDe(productoEnVenta($usuario->negocio)))->assertForbidden();
    expect(Venta::count())->toBe(0);
})->with(['cocina', 'garzon']);

test('sales require a confirmed branch when several are available', function () {
    $negocio = negocioDeVentas();
    Sucursal::factory()->for($negocio)->create();
    $this->actingAs(User::factory()->for($negocio)->admin()->create())->getJson('/api/v1/pos')->assertStatus(409)->assertJsonPath('code', 'sucursal_requerida');
});

test('the point of sale lists only the products on sale of the business with their modifier groups', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $cola = productoEnVenta($negocio, 1500, ['nombre' => 'Cola', 'codigo_barras' => '780123']);
    $grupo = GrupoModificador::factory()->for($negocio)->create(['nombre' => 'Salsas', 'tipo_seleccion' => 'cantidad', 'maximo' => 3, 'cantidad_gratis' => 1, 'precio_extra' => 200]);
    Modificador::factory()->for($grupo, 'grupo')->create(['nombre' => 'Mayo', 'precio_adicional' => 0, 'orden' => 0]);
    Modificador::factory()->for($grupo, 'grupo')->inactivo()->create(['nombre' => 'Oculta']);
    $cola->gruposModificadores()->attach($grupo, ['orden' => 0]);
    productoEnVenta($negocio, 100, ['nombre' => 'Inactivo', 'activo' => false]);
    Producto::factory()->for($negocio)->insumo()->create(['nombre' => 'Palta']);
    Producto::factory()->create(['nombre' => 'Ajeno', 'precio' => 100, 'se_vende' => true]);
    $respuesta = $this->actingAs($cajero)->getJson('/api/v1/pos')->assertOk()->assertJsonCount(1, 'data.productos')
        ->assertJsonPath('data.productos.0.nombre', 'Cola')->assertJsonPath('data.productos.0.precio', 1500)->assertJsonPath('data.productos.0.unidad_venta', 'unidad')
        ->assertJsonPath('data.productos.0.codigo_barras', '780123')->assertJsonCount(1, 'data.productos.0.grupos_modificadores')
        ->assertJsonPath('data.productos.0.grupos_modificadores.0.tipo_seleccion', 'cantidad')->assertJsonCount(1, 'data.productos.0.grupos_modificadores.0.modificadores')
        ->assertJsonPath('data.productos.0.grupos_modificadores.0.modificadores.0.nombre', 'Mayo')->assertJsonCount(4, 'data.medios_pago')
        ->assertJsonPath('data.descuento_maximo', 10)->assertJsonPath('data.descuento_sin_limite', false)->assertJsonPath('data.turno.caja', 'Caja 1');
    expect(json_encode($respuesta->json()))->not->toContain('negocio_id');
});

test('modifier groups are hidden when the module is off', function () {
    $negocio = negocioDeVentas();
    $negocio->update(['modulos' => []]);
    $cajero = vendedorConTurno($negocio);
    $producto = productoEnVenta($negocio);
    $producto->gruposModificadores()->attach(GrupoModificador::factory()->for($negocio)->create(), ['orden' => 0]);
    $this->actingAs($cajero)->getJson('/api/v1/pos')->assertOk()->assertJsonPath('data.productos.0.grupos_modificadores', []);
});

test('a cash sale is registered with the server calculating the totals and the change', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $producto = productoEnVenta($negocio, 1500, ['nombre' => 'Bebida', 'sku' => 'BEB-1', 'costo' => 900]);
    $ajeno = Negocio::factory()->create();
    $respuesta = $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe($producto, ['negocio_id' => $ajeno->id, 'total' => 1, 'user_id' => 99, 'numero' => 50, 'notas' => 'Para llevar'], 2))
        ->assertCreated()->assertJsonPath('data.total', 3000)->assertJsonPath('data.subtotal', 3000)->assertJsonPath('data.redondeo', 0)->assertJsonPath('data.folio', 1)
        ->assertJsonPath('data.numero', 1)->assertJsonPath('data.estado', 'pagada')->assertJsonPath('data.vendedor', $cajero->name)->assertJsonPath('data.notas', 'Para llevar')
        ->assertJsonPath('data.pagos.0.medio', 'efectivo')->assertJsonPath('data.pagos.0.monto', 3000)->assertJsonPath('data.pagos.0.recibido', 5000)->assertJsonPath('data.pagos.0.vuelto', 2000)
        ->assertJsonPath('data.items.0.nombre', 'Bebida')->assertJsonPath('data.items.0.cantidad', '2.000')->assertJsonPath('data.items.0.total', 3000);
    $venta = Venta::sole();
    $item = $venta->items->sole();
    expect($venta->negocio_id)->toBe($negocio->id)->and($venta->user_id)->toBe($cajero->id)->and($venta->turno_caja_id)->toBe(TurnoCaja::sole()->id)
        ->and($item->nombre)->toBe('Bebida')->and($item->sku)->toBe('BEB-1')->and($item->precio_unitario)->toBe(1500)->and($item->costo_unitario)->toBe(900);
    expect(json_encode($respuesta->json()))->not->toContain('negocio_id');
    $producto->update(['precio' => 9999, 'nombre' => 'Cambiado']);
    expect($item->fresh()->precio_unitario)->toBe(1500)->and($item->fresh()->nombre)->toBe('Bebida');
});

test('cash payments are rounded to ten pesos but cards are not', function (int $precio, string $medio, int $totalEsperado, int $redondeo) {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $pagos = $medio === 'efectivo' ? [['medio' => 'efectivo', 'recibido' => 20000]] : [['medio' => $medio, 'monto' => $precio]];
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe(productoEnVenta($negocio, $precio), ['pagos' => $pagos]))
        ->assertCreated()->assertJsonPath('data.total', $totalEsperado)->assertJsonPath('data.redondeo', $redondeo)->assertJsonPath('data.subtotal', $precio);
})->with([
    'ends in 1 rounds down' => [1001, 'efectivo', 1000, -1], 'ends in 5 rounds down' => [1005, 'efectivo', 1000, -5], 'ends in 6 rounds up' => [1006, 'efectivo', 1010, 4],
    'ends in 9 rounds up' => [1009, 'efectivo', 1010, 1], 'exact stays' => [1010, 'efectivo', 1010, 0], 'debit is not rounded' => [1006, 'debito', 1006, 0],
    'credit is not rounded' => [1004, 'credito', 1004, 0], 'transfer is not rounded' => [1009, 'transferencia', 1009, 0],
]);

test('payments can be split between several methods and cash covers the rest', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe(productoEnVenta($negocio, 5003), ['pagos' => [
        ['medio' => 'debito', 'monto' => 2000], ['medio' => 'efectivo', 'recibido' => 5000],
    ]]))->assertCreated()->assertJsonPath('data.total', 5000)->assertJsonPath('data.redondeo', -3)->assertJsonCount(2, 'data.pagos')
        ->assertJsonPath('data.pagos.0.medio', 'debito')->assertJsonPath('data.pagos.0.monto', 2000)
        ->assertJsonPath('data.pagos.1.medio', 'efectivo')->assertJsonPath('data.pagos.1.monto', 3000)->assertJsonPath('data.pagos.1.vuelto', 2000);
    $this->postJson('/api/v1/ventas', ventaDe(productoEnVenta($negocio, 3000), ['pagos' => [['medio' => 'debito', 'monto' => 1000], ['medio' => 'transferencia', 'monto' => 2000]]]))
        ->assertCreated()->assertJsonCount(2, 'data.pagos')->assertJsonPath('data.total', 3000);
});

test('invalid payments are rejected', function (array $pagos) {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe(productoEnVenta($negocio, 3000), ['pagos' => $pagos]))->assertUnprocessable();
    expect(Venta::count())->toBe(0);
})->with([
    'no payments' => [[]], 'unknown method' => [[['medio' => 'cheque', 'monto' => 3000]]], 'card without amount' => [[['medio' => 'debito']]],
    'card below total' => [[['medio' => 'debito', 'monto' => 2000]]], 'card above total' => [[['medio' => 'debito', 'monto' => 3500]]],
    'repeated method' => [[['medio' => 'debito', 'monto' => 1500], ['medio' => 'debito', 'monto' => 1500]]], 'two cash payments' => [[['medio' => 'efectivo', 'recibido' => 5000], ['medio' => 'efectivo', 'recibido' => 5000]]],
    'cash received is not enough' => [[['medio' => 'efectivo', 'recibido' => 2990]]], 'cash when total is covered' => [[['medio' => 'debito', 'monto' => 3000], ['medio' => 'efectivo', 'recibido' => 100]]],
    'decimal amount' => [[['medio' => 'debito', 'monto' => 3000.5]]], 'negative received' => [[['medio' => 'efectivo', 'recibido' => -1]]],
]);

test('weight products are sold with decimals and the line is rounded to the peso', function (string $cantidad, int $precio, int $subtotal) {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $producto = productoEnVenta($negocio, $precio, ['unidad_venta' => 'kg']);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe($producto, ['pagos' => [['medio' => 'debito', 'monto' => $subtotal]]], $cantidad))
        ->assertCreated()->assertJsonPath('data.subtotal', $subtotal)->assertJsonPath('data.items.0.cantidad', bcadd($cantidad, '0', 3));
})->with(['grams' => ['0.318', 8000, 2544], 'half peso rounds up' => ['1.5', 1999, 2999], 'whole kilos' => ['2', 1250, 2500], 'small amount' => ['0.001', 4999, 5]]);

test('invalid lines are rejected', function (Closure $armar, string $campo) {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $datos = $armar($negocio);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', $datos)->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(Venta::count())->toBe(0);
})->with([
    'no items' => [fn ($n) => ['items' => [], 'pagos' => [['medio' => 'efectivo']]], 'items'],
    'unknown product' => [fn ($n) => ventaDe(new Producto(['nombre' => 'x']) ?: null, ['items' => [['producto' => '01m5n22fn4rjjt0zc1mwgd9faa', 'cantidad' => 1]]]), 'items.0.producto'],
    'foreign product' => [fn ($n) => ventaDe(Producto::factory()->create(['precio' => 100, 'se_vende' => true])), 'items.0.producto'],
    'inactive product' => [fn ($n) => ventaDe(productoEnVenta($n, 1000, ['activo' => false])), 'items.0.producto'],
    'supply only product' => [fn ($n) => ventaDe(Producto::factory()->for($n)->insumo()->create()), 'items.0.producto'],
    'zero quantity' => [fn ($n) => ventaDe(productoEnVenta($n), [], 0), 'items.0.cantidad'],
    'negative quantity' => [fn ($n) => ventaDe(productoEnVenta($n), [], -1), 'items.0.cantidad'],
    'four decimals' => [fn ($n) => ventaDe(productoEnVenta($n, 1000, ['unidad_venta' => 'kg']), [], '0.1234'), 'items.0.cantidad'],
    'decimals on units' => [fn ($n) => ventaDe(productoEnVenta($n), [], '1.5'), 'items.0.cantidad'],
    'huge quantity' => [fn ($n) => ventaDe(productoEnVenta($n), [], 100000), 'items.0.cantidad'],
    'text quantity' => [fn ($n) => ventaDe(productoEnVenta($n), [], 'mucho'), 'items.0.cantidad'],
    'unknown modifier' => [fn ($n) => ventaDe(productoEnVenta($n), ['items' => [['producto' => productoEnVenta($n)->public_id, 'cantidad' => 1, 'modificadores' => [['id' => '01m5n22fn4rjjt0zc1mwgd9faa', 'cantidad' => 1]]]]]), 'items.0.modificadores'],
    'long line note' => [fn ($n) => ventaDe(productoEnVenta($n), ['items' => [['producto' => productoEnVenta($n)->public_id, 'cantidad' => 1, 'notas' => str_repeat('x', 256)]]]), 'items.0.notas'],
]);

test('modifiers are charged and saved following the group rules', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $completo = productoEnVenta($negocio, 3000, ['nombre' => 'Completo']);
    $pan = GrupoModificador::factory()->for($negocio)->create(['nombre' => 'Pan', 'tipo_seleccion' => 'unica', 'minimo' => 1, 'maximo' => 1]);
    $amasado = Modificador::factory()->for($pan, 'grupo')->create(['nombre' => 'Amasado', 'precio_adicional' => 0, 'orden' => 0]);
    $integral = Modificador::factory()->for($pan, 'grupo')->create(['nombre' => 'Integral', 'precio_adicional' => 300, 'orden' => 1]);
    $salsas = GrupoModificador::factory()->for($negocio)->create(['nombre' => 'Salsas', 'tipo_seleccion' => 'cantidad', 'maximo' => 4, 'cantidad_gratis' => 2, 'precio_extra' => 200]);
    $mayo = Modificador::factory()->for($salsas, 'grupo')->create(['nombre' => 'Mayo', 'precio_adicional' => 0, 'orden' => 0]);
    $aji = Modificador::factory()->for($salsas, 'grupo')->create(['nombre' => 'Aji', 'precio_adicional' => 100, 'cantidad_maxima' => 3, 'orden' => 1]);
    $completo->gruposModificadores()->attach($pan, ['orden' => 0]);
    $completo->gruposModificadores()->attach($salsas, ['orden' => 1]);
    $linea = fn (array $modificadores, int $cantidad = 2) => ['items' => [['producto' => $completo->public_id, 'cantidad' => $cantidad, 'modificadores' => $modificadores]], 'pagos' => [['medio' => 'efectivo', 'recibido' => 99999]]];

    // Por unidad: 3000 + pan integral 300 + 3 salsas (2 gratis, 1 con extra 200) con ají 100 + mayo 0 + 200 = 3000 + 300 + 100 + 200 = 3600; por 2 = 7200.
    $this->actingAs($cajero)->postJson('/api/v1/ventas', [...$linea([['id' => $integral->public_id, 'cantidad' => 1], ['id' => $mayo->public_id, 'cantidad' => 2], ['id' => $aji->public_id, 'cantidad' => 1]]),
        'pagos' => [['medio' => 'debito', 'monto' => 7200]]])
        ->assertCreated()->assertJsonPath('data.subtotal', 7200)->assertJsonPath('data.items.0.modificadores_unitario', 600)->assertJsonCount(3, 'data.items.0.modificadores')
        ->assertJsonPath('data.items.0.modificadores.0.grupo', 'Pan')->assertJsonPath('data.items.0.modificadores.0.nombre', 'Integral')->assertJsonPath('data.items.0.modificadores.0.total', 300)
        ->assertJsonPath('data.items.0.modificadores.2.total', 300);

    foreach ([
        'required group missing' => [[['id' => $mayo->public_id, 'cantidad' => 1]], 'Elige una opción en "Pan".'],
        'two options in a single group' => [[['id' => $amasado->public_id, 'cantidad' => 1], ['id' => $integral->public_id, 'cantidad' => 1]], 'Puedes elegir hasta 1 en "Pan".'],
        'over the group maximum' => [[['id' => $amasado->public_id, 'cantidad' => 1], ['id' => $mayo->public_id, 'cantidad' => 3], ['id' => $aji->public_id, 'cantidad' => 2]], 'Puedes elegir hasta 4 en "Salsas".'],
        'over the option maximum' => [[['id' => $amasado->public_id, 'cantidad' => 1], ['id' => $aji->public_id, 'cantidad' => 4]], 'Máximo 3 de "Aji".'],
        'repeated option' => [[['id' => $amasado->public_id, 'cantidad' => 1], ['id' => $mayo->public_id, 'cantidad' => 1], ['id' => $mayo->public_id, 'cantidad' => 1]], '"Mayo" está repetido en "Salsas".'],
    ] as [$modificadores, $mensaje]) {
        $respuesta = $this->postJson('/api/v1/ventas', $linea($modificadores))->assertUnprocessable();
        expect($respuesta->json('errors')['items.0.modificadores'][0] ?? json_encode($respuesta->json('errors')))->toBe($mensaje);
    }
    expect(Venta::count())->toBe(1);
});

test('inactive or foreign modifiers cannot be sold', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $producto = productoEnVenta($negocio);
    $grupo = GrupoModificador::factory()->for($negocio)->create();
    $inactivo = Modificador::factory()->for($grupo, 'grupo')->inactivo()->create();
    $ajeno = Modificador::factory()->create();
    $producto->gruposModificadores()->attach($grupo, ['orden' => 0]);
    foreach ([$inactivo, $ajeno] as $modificador) {
        $this->actingAs($cajero)->postJson('/api/v1/ventas', ['items' => [['producto' => $producto->public_id, 'cantidad' => 1, 'modificadores' => [['id' => $modificador->public_id, 'cantidad' => 1]]]],
            'pagos' => [['medio' => 'efectivo', 'recibido' => 9000]]])->assertUnprocessable()->assertJsonValidationErrors('items.0.modificadores');
    }
    expect(Venta::count())->toBe(0);
});

test('line and sale discounts are split exactly and need a reason', function () {
    $negocio = negocioDeVentas(['descuento_maximo_cajero' => 50]);
    $cajero = vendedorConTurno($negocio);
    $a = productoEnVenta($negocio, 3333);
    $b = productoEnVenta($negocio, 3333);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', [
        'items' => [['producto' => $a->public_id, 'cantidad' => 1, 'descuento' => ['tipo' => 'porcentaje', 'valor' => 10]], ['producto' => $b->public_id, 'cantidad' => 1]],
        'descuento' => ['tipo' => 'monto', 'valor' => 1001], 'motivo_descuento' => '  Cliente frecuente  ', 'pagos' => [['medio' => 'debito', 'monto' => 5000]],
    ])->assertUnprocessable()->assertJsonValidationErrors('pagos');
    // Línea A: 3333 - 333 = 3000; línea B: 3333; base 6333; descuento 1001 repartido; total = 6666 - 333 - 1001 = 5332.
    $respuesta = $this->postJson('/api/v1/ventas', [
        'items' => [['producto' => $a->public_id, 'cantidad' => 1, 'descuento' => ['tipo' => 'porcentaje', 'valor' => 10]], ['producto' => $b->public_id, 'cantidad' => 1]],
        'descuento' => ['tipo' => 'monto', 'valor' => 1001], 'motivo_descuento' => '  Cliente frecuente  ', 'pagos' => [['medio' => 'debito', 'monto' => 5332]],
    ])->assertCreated()->assertJsonPath('data.subtotal', 6666)->assertJsonPath('data.descuento', 1334)->assertJsonPath('data.total', 5332)->assertJsonPath('data.descuento_motivo', 'Cliente frecuente');
    expect(array_sum(array_column($respuesta->json('data.items'), 'total')))->toBe(5332)->and(array_sum(array_column($respuesta->json('data.items'), 'descuento')))->toBe(1334);
    $this->postJson('/api/v1/ventas', ventaDe($a, ['descuento' => ['tipo' => 'monto', 'valor' => 100]]))->assertUnprocessable()->assertJsonValidationErrors('motivo_descuento');
});

test('invalid discounts are rejected', function (array $descuento, string $campo) {
    $negocio = negocioDeVentas(['descuento_maximo_cajero' => 100]);
    $cajero = vendedorConTurno($negocio);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe(productoEnVenta($negocio, 1000), ['descuento' => $descuento, 'motivo_descuento' => 'Prueba']))->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(Venta::count())->toBe(0);
})->with([
    'unknown type' => [['tipo' => 'regalo', 'valor' => 10], 'descuento.tipo'], 'zero percentage' => [['tipo' => 'porcentaje', 'valor' => 0], 'descuento.valor'],
    'percentage above 100' => [['tipo' => 'porcentaje', 'valor' => 101], 'descuento'], 'amount above the sale' => [['tipo' => 'monto', 'valor' => 1001], 'descuento'],
    'covers the whole sale' => [['tipo' => 'porcentaje', 'valor' => 100], 'descuento'], 'decimal amount' => [['tipo' => 'monto', 'valor' => 10.5], 'descuento.valor'],
    'missing value' => [['tipo' => 'monto'], 'descuento.valor'],
]);

test('cashiers have a discount limit but users with the permission do not', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $admin = vendedorConTurno($negocio, 'admin');
    $producto = productoEnVenta($negocio, 10000);
    $con = fn (int $porcentaje) => ventaDe($producto, ['descuento' => ['tipo' => 'porcentaje', 'valor' => $porcentaje], 'motivo_descuento' => 'Promo', 'pagos' => [['medio' => 'debito', 'monto' => 10000 - $porcentaje * 100]]]);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', $con(20))->assertUnprocessable()->assertJsonPath('errors.descuento.0', 'Puedes descontar hasta un 10% de la venta. Pide a un administrador un descuento mayor.');
    $this->postJson('/api/v1/ventas', $con(10))->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/ventas', $con(50))->assertCreated()->assertJsonPath('data.total', 5000);
});

test('repeating a sale with the same idempotency key does not duplicate it', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $datos = ventaDe(productoEnVenta($negocio, 3000), ['clave_idempotencia' => 'abc-123_XYZ']);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', $datos)->assertCreated()->assertJsonPath('data.folio', 1);
    $this->postJson('/api/v1/ventas', $datos)->assertOk()->assertJsonPath('data.folio', 1);
    expect(Venta::count())->toBe(1);
    $this->postJson('/api/v1/ventas', [...$datos, 'clave_idempotencia' => 'otra'])->assertCreated()->assertJsonPath('data.folio', 2);
    $this->postJson('/api/v1/ventas', [...$datos, 'clave_idempotencia' => 'con espacios'])->assertUnprocessable()->assertJsonValidationErrors('clave_idempotencia');
});

test('a sale needs the open shift of the seller', function (string $caso) {
    $negocio = negocioDeVentas();
    $cajero = User::factory()->for($negocio)->cajero()->create();
    if ($caso === 'someone elses shift') {
        TurnoCaja::factory()->create(['caja_id' => Caja::where('negocio_id', $negocio->id)->firstOrFail()->id, 'user_id' => User::factory()->for($negocio)->cajero()->create()->id]);
    }
    if ($caso === 'closed shift') {
        TurnoCaja::factory()->cerrado()->create(['caja_id' => Caja::where('negocio_id', $negocio->id)->firstOrFail()->id, 'user_id' => $cajero->id]);
    }
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe(productoEnVenta($negocio)))->assertUnprocessable()->assertJsonValidationErrors('turno');
    expect(Venta::count())->toBe(0);
})->with(['no shift', 'someone elses shift', 'closed shift']);

test('folios are consecutive per branch and per business and independent between businesses', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $producto = productoEnVenta($negocio);
    $otro = negocioDeVentas();
    $otroCajero = vendedorConTurno($otro);
    $productoDelOtro = productoEnVenta($otro);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe($producto))->assertCreated()->assertJsonPath('data.folio', 1)->assertJsonPath('data.numero', 1);
    $this->postJson('/api/v1/ventas', ventaDe($producto))->assertCreated()->assertJsonPath('data.folio', 2)->assertJsonPath('data.numero', 2);
    $this->actingAs($otroCajero)->postJson('/api/v1/ventas', ventaDe($productoDelOtro))->assertCreated()->assertJsonPath('data.folio', 1);
    expect(Venta::withoutGlobalScopes()->count())->toBe(3);
});

test('cash sales raise the expected cash of the shift but card sales do not', function () {
    $negocio = negocioDeVentas(['cierre_ciego' => false]);
    $cajero = vendedorConTurno($negocio, 'cajero', 10000);
    $producto = productoEnVenta($negocio, 3000);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe($producto))->assertCreated();
    $this->postJson('/api/v1/ventas', ventaDe($producto, ['pagos' => [['medio' => 'debito', 'monto' => 3000]]]))->assertCreated();
    $this->postJson('/api/v1/ventas', ventaDe($producto, ['pagos' => [['medio' => 'debito', 'monto' => 1000], ['medio' => 'efectivo', 'recibido' => 2000]]]))->assertCreated();
    $this->getJson('/api/v1/caja')->assertOk()->assertJsonPath('data.turno.ventas_efectivo', 5000)->assertJsonPath('data.turno.monto_esperado', 15000);
    $this->postJson('/api/v1/caja/movimientos', ['tipo' => 'retiro', 'monto' => 15001, 'motivo' => 'Compra'])->assertUnprocessable();
    $this->postJson('/api/v1/caja/movimientos', ['tipo' => 'retiro', 'monto' => 15000, 'motivo' => 'Compra'])->assertCreated();
    $this->postJson('/api/v1/caja/cerrar', ['monto_contado' => 0])->assertOk()->assertJsonPath('data.monto_esperado', 0)->assertJsonPath('data.diferencia', 0)->assertJsonPath('data.ventas_efectivo', 5000);
});

test('with the inventory add-on a sale discounts the branch stock and may leave it negative', function () {
    $negocio = negocioDeVentas([], true);
    $cajero = vendedorConTurno($negocio);
    $controlado = Producto::factory()->for($negocio)->create(['precio' => 1000, 'se_vende' => true, 'controla_stock' => true, 'unidad_venta' => 'kg']);
    $libre = productoEnVenta($negocio, 500);
    $sucursal = $negocio->sucursales()->sole();
    Stock::factory()->create(['producto_id' => $controlado->id, 'negocio_id' => $negocio->id, 'sucursal_id' => $sucursal->id, 'cantidad' => '2.000']);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ['items' => [
        ['producto' => $controlado->public_id, 'cantidad' => '1.500'], ['producto' => $libre->public_id, 'cantidad' => 1], ['producto' => $controlado->public_id, 'cantidad' => '1.000'],
    ], 'pagos' => [['medio' => 'debito', 'monto' => 3000]]])->assertCreated();
    $movimiento = MovimientoStock::sole();
    expect($movimiento->tipo)->toBe('venta')->and($movimiento->cantidad)->toBe('-2.500')->and($movimiento->stock_resultante)->toBe('-0.500')
        ->and($movimiento->motivo)->toBe('Venta N° 1')->and($movimiento->venta_id)->toBe(Venta::sole()->id)->and(Stock::where('producto_id', $controlado->id)->sole()->cantidad)->toBe('-0.500');
    expect(Stock::where('producto_id', $libre->id)->count())->toBe(0);
});

test('without the inventory add-on a sale does not touch the stock', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $producto = Producto::factory()->for($negocio)->create(['precio' => 1000, 'se_vende' => true, 'controla_stock' => true]);
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe($producto))->assertCreated();
    expect(Stock::count())->toBe(0)->and(MovimientoStock::count())->toBe(0);
});

test('the sales history shows each seller their own sales and managers all of the branch', function () {
    $negocio = negocioDeVentas();
    $ajena = Venta::factory()->create();
    $ana = vendedorConTurno($negocio);
    $producto = productoEnVenta($negocio, 3000);
    $this->actingAs($ana)->postJson('/api/v1/ventas', ventaDe($producto))->assertCreated();
    $this->postJson('/api/v1/ventas', ventaDe($producto, ['pagos' => [['medio' => 'debito', 'monto' => 3000]]]))->assertCreated();
    $admin = vendedorConTurno($negocio, 'admin');
    $this->actingAs($admin)->postJson('/api/v1/ventas', ventaDe($producto))->assertCreated();
    $propias = Venta::withoutGlobalScopes()->where('user_id', $ana->id)->get();
    $this->actingAs($ana)->getJson('/api/v1/ventas')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.folio', 2)->assertJsonPath('data.0.items_count', 1)
        ->assertJsonPath('data.0.pagos.0.etiqueta', 'Débito')->assertJsonPath('usuarios', []);
    $this->getJson('/api/v1/ventas/'.$propias->first()->public_id)->assertOk()->assertJsonCount(1, 'data.items');
    $this->getJson('/api/v1/ventas/'.Venta::withoutGlobalScopes()->where('user_id', $admin->id)->sole()->public_id)->assertNotFound();
    $this->getJson('/api/v1/ventas/'.$ajena->public_id)->assertNotFound();
    $this->actingAs($admin)->getJson('/api/v1/ventas')->assertOk()->assertJsonCount(3, 'data')->assertJsonCount(2, 'usuarios');
    $this->getJson('/api/v1/ventas/'.$propias->first()->public_id)->assertOk();
    $this->getJson('/api/v1/ventas/'.$ajena->public_id)->assertNotFound();
});

test('the sales history can be filtered', function () {
    $negocio = negocioDeVentas();
    $admin = vendedorConTurno($negocio, 'admin');
    $ana = User::factory()->for($negocio)->cajero()->create();
    $turno = TurnoCaja::sole();
    $antigua = Venta::factory()->create(['turno_caja_id' => $turno->id, 'user_id' => $ana->id, 'pagada_at' => '2026-10-01 10:00:00']);
    $reciente = Venta::factory()->create(['turno_caja_id' => $turno->id, 'user_id' => $admin->id, 'pagada_at' => '2026-10-20 10:00:00']);
    Pago::factory()->create(['venta_id' => $antigua->id, 'medio' => 'debito', 'recibido' => null]);
    Pago::factory()->create(['venta_id' => $reciente->id, 'medio' => 'efectivo']);
    $this->actingAs($admin);
    $this->getJson('/api/v1/ventas?folio='.$reciente->numero_sucursal)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $reciente->public_id);
    $this->getJson('/api/v1/ventas?usuario='.$ana->public_id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $antigua->public_id);
    $this->getJson('/api/v1/ventas?medio=debito')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $antigua->public_id);
    $this->getJson('/api/v1/ventas?desde=2026-10-10')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $reciente->public_id);
    $this->getJson('/api/v1/ventas?hasta=2026-10-10')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $antigua->public_id);
    $this->getJson('/api/v1/ventas?medio=cheque')->assertUnprocessable();
    $this->getJson('/api/v1/ventas?desde=2026-10-10&hasta=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('hasta');
});

test('sales cannot be registered when the business is read only', function () {
    $negocio = negocioDeVentas();
    $cajero = vendedorConTurno($negocio);
    $negocio->subscriptions ?? null;
    $negocio->suscripciones()->delete();
    $negocio->forceFill(['prueba_desde' => now()->subDays(30), 'prueba_hasta' => now()->subDays(16)])->save();
    $this->actingAs($cajero)->postJson('/api/v1/ventas', ventaDe(productoEnVenta($negocio)))->assertForbidden()->assertJsonPath('code', 'negocio_solo_lectura');
    expect(Venta::count())->toBe(0);
});
