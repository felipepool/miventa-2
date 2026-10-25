<?php

use App\Enums\Plan;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\NegocioAdicional;
use App\Models\Sucursal;
use App\Models\SucursalAdicional;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminPrueba(): User
{
    Tarifa::where('concepto', 'plan')->update(['precio_uf' => '1.0000']);

    return User::factory()->for(Negocio::factory()->plan(Plan::Retail)->conSucursalPrincipal())->admin()->create();
}

function contratarPrueba(array $adicionales = [], array $excluir = []): array
{
    $query = http_build_query(['plan' => 'retail', 'adicionales' => $adicionales, 'excluir' => $excluir]);
    $cotizacion = test()->getJson('/api/v1/plan/cotizacion?'.$query)->assertOk()->json('data.cotizacion');

    return ['plan' => 'retail', 'adicionales' => $adicionales, 'excluir' => $excluir, 'acepto_cobro' => true, 'cotizacion' => $cotizacion];
}

test('a trial lasts fourteen days and expires at its exact boundary', function () {
    $this->freezeTime();
    $admin = adminPrueba();
    $negocio = $admin->negocio;
    expect($negocio->prueba_hasta->equalTo($negocio->prueba_desde->addDays(14)))->toBeTrue();
    $this->actingAs($admin)->getJson('/api/v1/me')->assertJsonPath('data.contexto.suscripcion.en_prueba', true)
        ->assertJsonPath('data.contexto.suscripcion.adicionales_activos', ['documentos', 'mesas', 'kds', 'inventario'])
        ->assertJsonPath('data.contexto.suscripcion.facturacion.valor_tributario', false);
    $this->travelTo($negocio->prueba_hasta);
    $this->getJson('/api/v1/negocio')->assertOk();
    $this->putJson('/api/v1/negocio', ['nombre' => 'No debe guardarse'])->assertForbidden()->assertJsonPath('code', 'negocio_solo_lectura');
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.suscripcion.solo_lectura', true)
        ->assertJsonPath('data.contexto.suscripcion.adicionales_activos', []);
    expect($negocio->fresh()->nombre)->not->toBe('No debe guardarse');
});

test('the assigned plan can be contracted without shortening the trial or changing module preferences', function () {
    $this->freezeTime();
    $admin = adminPrueba();
    $admin->negocio->update(['modulos' => []]);
    $this->actingAs($admin);
    $payload = contratarPrueba(['documentos', 'kds']);
    $this->putJson('/api/v1/plan', $payload)->assertOk()->assertJsonPath('data.total_mensual_uf', '2.0000');
    $contrato = Suscripcion::sole();
    expect($contrato->desde->equalTo($admin->negocio->prueba_hasta))->toBeTrue()
        ->and($admin->negocio->fresh()->modulosActivos())->toBe([]);
    Tarifa::where('concepto', 'documentos')->update(['precio_uf' => '0.9000']);
    $this->getJson('/api/v1/plan')->assertJsonPath('data.total_mensual_uf', '2.0000');
    $this->travelTo($admin->negocio->prueba_hasta);
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.suscripcion.solo_lectura', false)
        ->assertJsonPath('data.contexto.suscripcion.adicionales_activos', ['documentos', 'kds'])
        ->assertJsonPath('data.contexto.suscripcion.facturacion.ambiente', 'certificacion');
    $this->putJson('/api/v1/negocio', ['nombre' => 'Operativo'])->assertOk();
    expect(NegocioAdicional::count())->toBe(2);
});

test('an expired trial can contract and recover operation without renewing the trial', function () {
    $this->freezeTime();
    $admin = adminPrueba();
    $fin = $admin->negocio->prueba_hasta;
    $this->travelTo($fin->addDays(2));
    $this->actingAs($admin);
    $this->putJson('/api/v1/plan', contratarPrueba())->assertOk();
    expect(Suscripcion::sole()->desde->equalTo(now()))->toBeTrue()
        ->and($admin->negocio->fresh()->prueba_hasta->equalTo($fin))->toBeTrue();
    $this->putJson('/api/v1/negocio', ['nombre' => 'Recuperado'])->assertOk();
});

test('trial resources have no contracts until their complete monthly quote is accepted', function () {
    $this->freezeTime();
    $admin = adminPrueba();
    $principal = $admin->negocio->sucursales()->sole();
    $this->actingAs($admin)->postJson('/api/v1/cajas', ['sucursal' => $principal->public_id, 'nombre' => 'Extra'])->assertCreated();
    expect(CajaAdicional::count())->toBe(0);
    $this->postJson('/api/v1/sucursales', ['nombre' => 'Norte'])->assertCreated();
    expect(SucursalAdicional::count())->toBe(0);
    $this->putJson('/api/v1/sucursal-actual/'.$principal->public_id)->assertOk();
    $this->getJson('/api/v1/plan/cotizacion?plan=retail')->assertJsonPath('data.total_mensual_uf', '1.5000');
    $this->putJson('/api/v1/plan', contratarPrueba())->assertOk();
    expect(CajaAdicional::count())->toBe(1)->and(SucursalAdicional::count())->toBe(1)
        ->and(CajaAdicional::sole()->desde->equalTo($admin->negocio->prueba_hasta))->toBeTrue();
});

test('acceptance is invalidated when the selected additional price or active resources change', function () {
    $admin = adminPrueba();
    $this->actingAs($admin);
    $payload = contratarPrueba(['mesas']);
    Tarifa::where('concepto', 'mesas')->where('plan', Plan::Retail)->update(['precio_uf' => '0.8000']);
    $this->putJson('/api/v1/plan', $payload)->assertUnprocessable()->assertJsonValidationErrors('cotizacion');
    $payload = contratarPrueba();
    Sucursal::factory()->for($admin->negocio)->create();
    $this->putJson('/api/v1/sucursal-actual/'.$admin->negocio->sucursales()->where('es_principal', true)->sole()->public_id)->assertOk();
    $this->putJson('/api/v1/plan', $payload)->assertUnprocessable()->assertJsonValidationErrors('cotizacion');
    expect(Suscripcion::count())->toBe(0);
});

test('a scheduled additional removed during trial expires without being charged', function () {
    $this->freezeTime();
    $admin = adminPrueba();
    $this->actingAs($admin);
    $this->putJson('/api/v1/plan', contratarPrueba(['mesas']))->assertOk();
    $this->putJson('/api/v1/plan', contratarPrueba())->assertOk();
    $contrato = NegocioAdicional::sole();
    expect($contrato->hasta->equalTo($contrato->desde))->toBeTrue();
    $this->travelTo($admin->negocio->prueba_hasta);
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.suscripcion.adicionales_activos', []);
});

test('a repeated accepted quote does not duplicate subscription or extras', function () {
    $admin = adminPrueba();
    $this->actingAs($admin);
    $payload = contratarPrueba(['kds']);
    $this->putJson('/api/v1/plan', $payload)->assertOk();
    $this->putJson('/api/v1/plan', $payload)->assertUnprocessable();
    expect(Suscripcion::count())->toBe(1)->and(NegocioAdicional::count())->toBe(1);
});

test('an expired business can decline extra resources only through its accepted contract', function () {
    $this->freezeTime();
    $admin = adminPrueba();
    $principal = $admin->negocio->sucursales()->sole();
    $extra = Sucursal::factory()->for($admin->negocio)->create();
    $caja = Caja::factory()->for($principal)->create();
    $this->travelTo($admin->negocio->prueba_hasta);
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$principal->public_id)->assertOk();
    $this->patchJson('/api/v1/sucursales/'.$extra->public_id.'/estado', ['activa' => false])->assertForbidden();
    $excluir = ['sucursal:'.$extra->public_id, 'caja:'.$caja->public_id];
    $payload = contratarPrueba([], $excluir);
    $this->putJson('/api/v1/plan', [...$payload, 'acepto_cobro' => false])->assertUnprocessable();
    expect($extra->fresh()->activa)->toBeTrue()->and($caja->fresh()->activa)->toBeTrue();
    $this->putJson('/api/v1/plan', $payload)->assertOk()->assertJsonPath('data.total_mensual_uf', '1.0000');
    expect($extra->fresh()->activa)->toBeFalse()->and($caja->fresh()->activa)->toBeFalse()
        ->and($principal->fresh()->activa)->toBeTrue()
        ->and(SucursalAdicional::count())->toBe(0)->and(CajaAdicional::count())->toBe(0);
});

test('a contract cannot deactivate another tenant resources or the included branch', function () {
    $admin = adminPrueba();
    $ajena = Sucursal::factory()->create();
    $this->actingAs($admin);
    foreach ([$ajena, $admin->negocio->sucursales()->sole()] as $sucursal) {
        $this->putJson('/api/v1/plan', [...contratarPrueba(), 'excluir' => ['sucursal:'.$sucursal->public_id]])
            ->assertUnprocessable()->assertJsonValidationErrors('excluir');
        expect($sucursal->fresh()->activa)->toBeTrue();
    }
    expect(Suscripcion::count())->toBe(0);
});

test('additional contract prices cannot be overwritten', function () {
    $admin = adminPrueba();
    $this->actingAs($admin)->putJson('/api/v1/plan', contratarPrueba(['documentos']))->assertOk();
    $contrato = NegocioAdicional::sole();
    expect(fn () => $contrato->forceFill(['precio_uf' => '0.0100'])->save())->toThrow(LogicException::class);
    expect($contrato->fresh()->precio_uf)->toBe('0.5000');
});
