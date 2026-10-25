<?php

use App\Enums\ConceptoTarifa;
use App\Models\MovimientoStock;
use App\Models\Negocio;
use App\Models\NegocioAdicional;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminSinInventario(): User
{
    return User::factory()->for(Negocio::factory()->conPlanContratado()->conSucursalPrincipal())->admin()->create();
}

function cotizacionConInventario(User $admin, array $adicionales = ['inventario']): array
{
    $consulta = http_build_query(['plan' => $admin->negocio->plan->value, 'adicionales' => $adicionales]);
    $cotizacion = test()->getJson('/api/v1/plan/cotizacion?'.$consulta)->assertOk()->json('data.cotizacion');

    return ['plan' => $admin->negocio->plan->value, 'adicionales' => $adicionales, 'acepto_cobro' => true, 'cotizacion' => $cotizacion];
}

test('the inventory tariff exists for every plan at half a UF per month', function () {
    expect(Tarifa::where('concepto', 'inventario')->pluck('precio_uf', 'plan')->all())
        ->toEqual(['retail' => '0.5000', 'comida' => '0.5000', 'mixto' => '0.5000']);
    expect(ConceptoTarifa::adicionales())->toContain(ConceptoTarifa::Inventario);
});

test('inventory is available during the trial without contracting it', function () {
    $admin = adminSinInventario();
    $admin->negocio->forceFill(['prueba_desde' => now()->subDays(2), 'prueba_hasta' => now()->addDays(12)])->save();
    $this->actingAs($admin)->getJson('/api/v1/inventario')->assertOk();
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.suscripcion.adicionales_activos', ['documentos', 'mesas', 'kds', 'inventario']);
});

test('inventory is rejected after the trial unless it was contracted', function () {
    $admin = adminSinInventario();
    $producto = Producto::factory()->for($admin->negocio)->create();
    $rutas = [['getJson', '/api/v1/inventario'], ['getJson', '/api/v1/inventario/'.$producto->public_id.'/movimientos'],
        ['postJson', '/api/v1/inventario/'.$producto->public_id.'/movimientos'], ['putJson', '/api/v1/inventario/'.$producto->public_id.'/minimo']];
    $this->actingAs($admin);
    foreach ($rutas as [$metodo, $ruta]) {
        $this->{$metodo}($ruta, ['tipo' => 'entrada', 'cantidad' => 1, 'stock_minimo' => 1])->assertForbidden()
            ->assertJsonPath('code', 'adicional_no_contratado')->assertJsonPath('adicional', 'inventario');
    }
    expect(MovimientoStock::count())->toBe(0)->and(Stock::count())->toBe(0);
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.suscripcion.adicionales_activos', []);
});

test('contracting the add-on from my plan enables the inventory and keeps the accepted price', function () {
    $admin = adminSinInventario();
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$admin->negocio->sucursales()->sole()->public_id)->assertOk();
    $cotizacion = $this->getJson('/api/v1/plan/cotizacion?plan='.$admin->negocio->plan->value.'&adicionales[]=inventario')->assertOk()
        ->assertJsonPath('data.adicionales.3.value', 'inventario')->assertJsonPath('data.adicionales.3.label', 'Inventario')
        ->assertJsonPath('data.adicionales.3.precio_uf', '0.5000')->assertJsonPath('data.adicionales.3.seleccionado', true);
    $this->putJson('/api/v1/plan', cotizacionConInventario($admin))->assertOk()->assertJsonPath('data.adicionales_contratados', ['inventario']);
    Tarifa::where('concepto', 'inventario')->update(['precio_uf' => '0.9000']);
    expect(NegocioAdicional::sole())->concepto->toBe(ConceptoTarifa::Inventario)->precio_uf->toBe('0.5000');
    $this->getJson('/api/v1/inventario')->assertOk();
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.suscripcion.adicionales_activos', ['inventario']);
});

test('the stock and history are kept when the add-on is dropped and come back when it is contracted again', function () {
    $admin = adminSinInventario();
    $admin->negocio->forceFill(['prueba_desde' => now()->subDays(2), 'prueba_hasta' => now()->addDays(12)])->save();
    $producto = Producto::factory()->for($admin->negocio)->create();
    $this->actingAs($admin)->postJson('/api/v1/inventario/'.$producto->public_id.'/movimientos', ['tipo' => 'ajuste', 'cantidad' => 8, 'motivo' => 'Conteo'])->assertCreated();

    $admin->negocio->forceFill(['prueba_desde' => now()->subDays(30), 'prueba_hasta' => now()->subDays(16)])->save();
    $this->getJson('/api/v1/inventario')->assertForbidden()->assertJsonPath('code', 'adicional_no_contratado');
    expect(Stock::sole()->cantidad)->toBe('8.000')->and(MovimientoStock::count())->toBe(1);

    $this->putJson('/api/v1/plan', cotizacionConInventario($admin))->assertOk();
    $this->getJson('/api/v1/inventario')->assertOk()->assertJsonPath('data.0.cantidad', '8.000');
    $this->getJson('/api/v1/inventario/'.$producto->public_id.'/movimientos')->assertOk()->assertJsonCount(1, 'data');

    $this->putJson('/api/v1/plan', cotizacionConInventario($admin, []))->assertOk()->assertJsonPath('data.adicionales_contratados', []);
    $this->getJson('/api/v1/inventario')->assertForbidden();
    expect(Stock::sole()->cantidad)->toBe('8.000');
});

test('a closed contract no longer enables the inventory', function () {
    $admin = User::factory()->for(Negocio::factory()->conPlanContratado()->conAdicional(ConceptoTarifa::Inventario)->conSucursalPrincipal())->admin()->create();
    $this->actingAs($admin)->getJson('/api/v1/inventario')->assertOk();
    NegocioAdicional::sole()->update(['hasta' => now()->subMinute()]);
    $this->getJson('/api/v1/inventario')->assertForbidden()->assertJsonPath('code', 'adicional_no_contratado');
});

test('the add-on does not replace the inventory permission', function () {
    $cajero = User::factory()->for(Negocio::factory()->conPlanContratado()->conAdicional(ConceptoTarifa::Inventario)->conSucursalPrincipal())->cajero()->create();
    $this->actingAs($cajero)->getJson('/api/v1/inventario')->assertForbidden()->assertJsonMissingPath('code');
});

test('one business contracting the add-on does not enable it for another', function () {
    $conAdicional = User::factory()->for(Negocio::factory()->conPlanContratado()->conAdicional(ConceptoTarifa::Inventario)->conSucursalPrincipal())->admin()->create();
    $sinAdicional = adminSinInventario();
    $this->actingAs($conAdicional)->getJson('/api/v1/inventario')->assertOk();
    $this->actingAs($sinAdicional)->getJson('/api/v1/inventario')->assertForbidden()->assertJsonPath('code', 'adicional_no_contratado');
});
