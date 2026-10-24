<?php

use App\Actions\Planes\ContratarPlan;
use App\Enums\Modulo;
use App\Enums\Plan;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\SucursalAdicional;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Tarifa::where('concepto', 'plan')->where('plan', Plan::Retail)->update(['precio_uf' => '0.5000']);
    Tarifa::where('concepto', 'plan')->where('plan', Plan::Comida)->update(['precio_uf' => '0.7000']);
});

function adminConPlan(Plan $plan): User
{
    $admin = User::factory()->for(Negocio::factory()->plan($plan)->conSucursalPrincipal())->admin()->create();
    app(ContratarPlan::class)->ejecutar($admin->negocio, $plan, $admin, '0.5000');

    return $admin;
}

/**
 * @return array{plan: string, acepto_cobro: bool, cotizacion: string}
 */
function cambioAceptado(Plan $plan): array
{
    $cotizacion = test()->getJson('/api/v1/plan/cotizacion?plan='.$plan->value)->assertOk()->json('data.cotizacion');

    return ['plan' => $plan->value, 'adicionales' => [], 'acepto_cobro' => true, 'cotizacion' => $cotizacion];
}

test('plan endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([['getJson', '/api/v1/plan'], ['getJson', '/api/v1/plan/cotizacion?plan=comida'], ['putJson', '/api/v1/plan']]);

test('operational users receive 403 when managing the plan', function () {
    $cajero = User::factory()->cajero()->create();
    $this->actingAs($cajero)->getJson('/api/v1/plan')->assertForbidden();
    $this->putJson('/api/v1/plan', ['plan' => 'comida', 'acepto_cobro' => true])->assertForbidden();
    expect($cajero->negocio->fresh()->plan)->toBe(Plan::Mixto);
});

test('the plan shows its contracted price and the monthly charge of its own business only', function () {
    $admin = adminConPlan(Plan::Retail);
    Tarifa::where('concepto', 'plan')->where('plan', Plan::Retail)->update(['precio_uf' => '0.9000']);
    $sucursal = Sucursal::factory()->for($admin->negocio)->create(['nombre' => 'Alameda']);
    SucursalAdicional::factory()->for($sucursal)->create();
    CajaAdicional::factory()->for(Caja::factory()->for($sucursal)->create(['nombre' => 'Mesón']))->create();
    CajaAdicional::factory()->create();
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$admin->negocio->sucursales()->where('es_principal', true)->sole()->public_id)->assertOk();
    $this->getJson('/api/v1/plan')->assertOk()
        ->assertJsonPath('data.plan.value', 'retail')
        ->assertJsonPath('data.plan.precio_uf', '0.5000')
        ->assertJsonCount(3, 'data.lineas')
        ->assertJsonPath('data.lineas.1', ['concepto' => 'Sucursal adicional', 'detalle' => 'Alameda', 'precio_uf' => '0.2500'])
        ->assertJsonPath('data.lineas.2.detalle', 'Mesón · Alameda')
        ->assertJsonPath('data.total_mensual_uf', '1.0000')
        ->assertJsonPath('data.opciones.1', ['value' => 'comida', 'label' => 'Gastronomía', 'descripcion' => Plan::Comida->descripcion(), 'precio_uf' => '0.7000', 'actual' => false])
        ->assertJsonCount(2, 'data.opciones');
});

test('a business without a contract shows its charge as pending even when catalog prices exist', function () {
    $admin = User::factory()->for(Negocio::factory()->plan(Plan::Retail)->conSucursalPrincipal())->admin()->create();
    $this->actingAs($admin)->getJson('/api/v1/plan')->assertOk()
        ->assertJsonPath('data.plan.desde', null)->assertJsonPath('data.lineas.0.detalle', 'Pendiente de contratación')->assertJsonPath('data.total_mensual_uf', null);
    Tarifa::where('concepto', 'plan')->update(['precio_uf' => null]);
    $this->getJson('/api/v1/plan')->assertOk()->assertJsonPath('data.total_mensual_uf', null);
});

test('changing the plan closes the old contract, snapshots the new price and activates its modules', function () {
    $admin = adminConPlan(Plan::Retail);
    $anterior = Suscripcion::sole();
    $contratoCaja = CajaAdicional::factory()->for(Caja::factory()->for($admin->negocio->sucursales()->sole())->create())->create();
    $this->actingAs($admin);
    $this->getJson('/api/v1/plan/cotizacion?plan=comida')->assertOk()
        ->assertJsonPath('data.precio_uf', '0.7000')
        ->assertJsonPath('data.modulos_agregados', [['value' => 'modificadores', 'label' => 'Modificadores'], ['value' => 'comanda', 'label' => 'Comanda y cocina']])
        ->assertJsonPath('data.modulos_retirados', [['value' => 'venta_por_peso', 'label' => 'Venta por peso']])
        ->assertJsonPath('data.sin_retorno', false);
    $this->putJson('/api/v1/plan', cambioAceptado(Plan::Comida))->assertOk()
        ->assertJsonPath('data.plan.value', 'comida')->assertJsonPath('data.plan.precio_uf', '0.7000');
    expect($anterior->fresh()->hasta)->not->toBeNull()
        ->and(Suscripcion::whereNull('hasta')->sole())->plan->toBe(Plan::Comida)->precio_uf->toBe('0.7000')->aceptado_por_id->toBe($admin->id)
        ->and($admin->negocio->fresh()->modulosActivos())->toBe([Modulo::Modificadores, Modulo::Comanda])
        ->and($contratoCaja->fresh())->hasta->toBeNull()->precio_uf->toBe('0.2500');
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.modulos', ['modificadores', 'comanda']);
});

test('a plan change requires accepting the current quote', function (array $cambios, string $campo) {
    $admin = adminConPlan(Plan::Retail);
    $this->actingAs($admin);
    $this->putJson('/api/v1/plan', [...cambioAceptado(Plan::Comida), ...$cambios])->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect($admin->negocio->fresh()->plan)->toBe(Plan::Retail)->and(Suscripcion::count())->toBe(1);
})->with([
    'not accepted' => [['acepto_cobro' => false], 'acepto_cobro'],
    'stale quote' => [['cotizacion' => str_repeat('a', 64)], 'cotizacion'],
    'unknown plan' => [['plan' => 'premium'], 'plan'],
]);

test('a quote is rejected after the price changes', function () {
    $admin = adminConPlan(Plan::Retail);
    $this->actingAs($admin);
    $payload = cambioAceptado(Plan::Comida);
    Tarifa::where('concepto', 'plan')->where('plan', Plan::Comida)->update(['precio_uf' => '0.8000']);
    $this->putJson('/api/v1/plan', $payload)->assertUnprocessable()
        ->assertJsonValidationErrors(['cotizacion' => 'Las condiciones han cambiado. Vuelve a revisar el plan antes de aceptar.']);
    expect($admin->negocio->fresh()->plan)->toBe(Plan::Retail);
});

test('a repeated submission does not open a second contract', function () {
    $admin = adminConPlan(Plan::Retail);
    $this->actingAs($admin);
    $payload = cambioAceptado(Plan::Comida);
    $this->putJson('/api/v1/plan', $payload)->assertOk();
    $this->putJson('/api/v1/plan', $payload)->assertUnprocessable()->assertJsonValidationErrors('cotizacion');
    expect(Suscripcion::count())->toBe(2)->and(Suscripcion::whereNull('hasta')->count())->toBe(1);
});

test('a plan without a configured price cannot be chosen', function () {
    $admin = adminConPlan(Plan::Retail);
    Tarifa::where('concepto', 'plan')->where('plan', Plan::Comida)->update(['precio_uf' => null]);
    $this->actingAs($admin);
    $this->putJson('/api/v1/plan', cambioAceptado(Plan::Comida))->assertUnprocessable()->assertJsonValidationErrors('tarifa');
    expect($admin->negocio->fresh()->plan)->toBe(Plan::Retail);
});

test('mixto is only kept by businesses that already have it', function () {
    $retail = adminConPlan(Plan::Retail);
    $mixto = User::factory()->for(Negocio::factory()->plan(Plan::Mixto)->conSucursalPrincipal())->admin()->create();
    $this->actingAs($retail)->putJson('/api/v1/plan', cambioAceptado(Plan::Mixto))->assertUnprocessable()->assertJsonValidationErrors('plan');
    $this->actingAs($mixto)->getJson('/api/v1/plan')->assertJsonCount(3, 'data.opciones')->assertJsonPath('data.opciones.2.actual', true);
    $this->getJson('/api/v1/plan/cotizacion?plan=retail')->assertJsonPath('data.sin_retorno', true);
});
