<?php

use App\Actions\Planes\CobroCajas;
use App\Enums\Plan;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('register endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([
    ['getJson', '/api/v1/cajas'], ['postJson', '/api/v1/cajas'],
    ['getJson', '/api/v1/cajas/01m5n22fn4rjjt0zc1mwgd9faa'],
    ['putJson', '/api/v1/cajas/01m5n22fn4rjjt0zc1mwgd9faa'],
    ['patchJson', '/api/v1/cajas/01m5n22fn4rjjt0zc1mwgd9faa/estado'],
    ['getJson', '/api/v1/cajas/cotizacion'],
]);

test('operational users receive 403 when managing registers', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();
    $caja = $usuario->negocio->sucursales()->sole()->cajas()->sole();
    $this->actingAs($usuario)->getJson('/api/v1/cajas')->assertForbidden();
    $this->postJson('/api/v1/cajas', ['nombre' => 'No creada'])->assertForbidden();
    $this->putJson('/api/v1/cajas/'.$caja->public_id, ['nombre' => 'No editada'])->assertForbidden();
    $this->patchJson('/api/v1/cajas/'.$caja->public_id.'/estado', ['activa' => false])->assertForbidden();
    expect($caja->fresh()->nombre)->toBe('Caja 1');
    expect(Caja::count())->toBe(1);
})->with(['cajero', 'cocina', 'garzon']);

test('register routes require a confirmed branch when several are available', function () {
    $admin = User::factory()->admin()->create();
    Sucursal::factory()->for($admin->negocio)->create();
    $this->actingAs($admin)->getJson('/api/v1/cajas')->assertStatus(409)->assertJsonPath('code', 'sucursal_requerida');
    $this->postJson('/api/v1/cajas', ['nombre' => 'No creada'])->assertStatus(409);
    $this->assertDatabaseMissing('cajas', ['nombre' => 'No creada']);
});

test('registers are paginated ordered by branch and filterable without leaking another business', function () {
    $admin = User::factory()->admin()->create();
    $principal = $admin->negocio->sucursales()->sole();
    $otra = Sucursal::factory()->for($admin->negocio)->create(['nombre' => 'Alameda']);
    Caja::factory()->for($otra)->count(16)->create();
    $inactiva = Caja::factory()->for($otra)->inactiva()->create(['nombre' => 'Mesón']);
    $ajena = Caja::factory()->create();
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$principal->public_id)->assertOk();
    $this->getJson('/api/v1/cajas')->assertOk()->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 19)->assertJsonPath('data.0.sucursal.id', $principal->public_id)
        ->assertJsonPath('data.0.es_incluida', true)->assertJsonCount(2, 'sucursales')
        ->assertJsonMissing(['id' => $ajena->public_id]);
    $this->getJson('/api/v1/cajas?sucursal='.$otra->public_id.'&estado=inactiva&buscar=mesón')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inactiva->public_id);
    $this->getJson('/api/v1/cajas?buscar='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
});

test('foreign register and branch requests return 404', function () {
    $admin = User::factory()->admin()->create();
    $ajena = Caja::factory()->create();
    $sucursalAjena = $ajena->sucursal->public_id;
    $this->actingAs($admin)->getJson('/api/v1/cajas/'.$ajena->public_id)->assertNotFound();
    $this->putJson('/api/v1/cajas/'.$ajena->public_id, ['nombre' => 'Ajena'])->assertNotFound();
    $this->patchJson('/api/v1/cajas/'.$ajena->public_id.'/estado', ['activa' => false])->assertNotFound();
    $this->postJson('/api/v1/cajas', ['nombre' => 'Ajena', 'sucursal' => $sucursalAjena])->assertNotFound();
    $this->getJson('/api/v1/cajas/cotizacion?sucursal='.$sucursalAjena)->assertNotFound();
    expect($ajena->fresh()->activa)->toBeTrue();
});

test('an additional register requires acceptance and snapshots the catalog price for every plan', function (Plan $plan) {
    $admin = User::factory()->for(Negocio::factory()->plan($plan)->conSucursalPrincipal())->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $this->actingAs($admin);
    $cotizacion = $this->getJson('/api/v1/cajas/cotizacion?sucursal='.$sucursal->public_id)->assertOk()
        ->assertJsonPath('data.requiere_aceptacion', true)->assertJsonPath('data.total_mensual_uf', '0.2500')->json('data.cotizacion');
    $payload = ['nombre' => ' Mesón ', 'sucursal' => $sucursal->public_id];
    $this->postJson('/api/v1/cajas', $payload)->assertUnprocessable()->assertJsonValidationErrors('acepto_cobro');
    $id = $this->postJson('/api/v1/cajas', [...$payload, 'acepto_cobro' => true, 'cotizacion' => $cotizacion,
        'es_incluida' => true, 'negocio_id' => 999, 'activa' => false])
        ->assertCreated()->assertJsonPath('data.nombre', 'Mesón')->assertJsonPath('data.activa', true)
        ->assertJsonPath('data.es_incluida', false)->assertJsonPath('data.precio_contratado_uf', '0.2500')->json('data.id');
    $contrato = CajaAdicional::sole();
    expect($contrato->aceptado_por_id)->toBe($admin->id);
    expect($contrato->plan)->toBe($plan);
    expect($sucursal->cajas()->count())->toBe(2);
    Tarifa::where('plan', $plan)->where('concepto', 'caja')->update(['precio_uf' => '0.5000']);
    $this->getJson('/api/v1/cajas/'.$id)->assertOk()->assertJsonPath('data.precio_contratado_uf', '0.2500');
})->with(Plan::cases());

test('invalid names and filters return 422', function (array $datos, string $campo) {
    $admin = User::factory()->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $this->actingAs($admin)->postJson('/api/v1/cajas', ['sucursal' => $sucursal->public_id, ...$datos])
        ->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(Caja::count())->toBe(1);
})->with([
    'empty' => [['nombre' => '   '], 'nombre'], 'too long' => [['nombre' => str_repeat('x', 51)], 'nombre'],
    'duplicate case' => [['nombre' => 'cAjA 1'], 'nombre'], 'invalid branch' => [['nombre' => 'Caja', 'sucursal' => '123'], 'sucursal'],
]);

test('renaming a register keeps its branch inclusion and contracted price', function () {
    $admin = User::factory()->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $caja = Caja::factory()->for($sucursal)->create();
    $contrato = CajaAdicional::factory()->for($caja)->create();
    $this->actingAs($admin)->putJson('/api/v1/cajas/'.$caja->public_id, ['nombre' => 'Mostrador'])
        ->assertOk()->assertJsonPath('data.nombre', 'Mostrador')->assertJsonPath('data.es_incluida', false)
        ->assertJsonPath('data.sucursal.id', $sucursal->public_id)->assertJsonPath('data.precio_contratado_uf', '0.2500');
    $this->putJson('/api/v1/cajas/'.$caja->public_id, ['nombre' => 'Otra', 'sucursal' => $sucursal->public_id])
        ->assertUnprocessable()->assertJsonValidationErrors('sucursal');
    expect($contrato->fresh()->hasta)->toBeNull();
    expect($caja->fresh()->sucursal_id)->toBe($sucursal->id);
});

test('deactivating the included register promotes another active register and closes its charge', function () {
    $this->travelTo(now()->setTimezone(config('facturacion.timezone'))->setDate(2026, 10, 15)->startOfDay());
    $admin = User::factory()->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $incluida = $sucursal->cajas()->sole();
    $extra = Caja::factory()->for($sucursal)->create();
    $contrato = CajaAdicional::factory()->for($extra)->create(['desde' => now()->setTimezone(config('facturacion.timezone'))->startOfMonth()]);
    $this->actingAs($admin)->patchJson('/api/v1/cajas/'.$incluida->public_id.'/estado', ['activa' => false])->assertOk();
    expect($extra->fresh()->es_incluida)->toBeTrue();
    expect($incluida->fresh()->es_incluida)->toBeFalse();
    expect($contrato->fresh()->hasta->toDateString())->toBe('2026-10-15');
    expect($contrato->fresh()->importeDelMes(now())['dias'])->toBe(14);
    expect(CajaAdicional::whereNull('hasta')->count())->toBe(0);
});

test('reactivating an old included register bills it without displacing the current included register', function () {
    $admin = User::factory()->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $antigua = $sucursal->cajas()->sole();
    $otra = Caja::factory()->for($sucursal)->create();
    CajaAdicional::factory()->for($otra)->create();
    $this->actingAs($admin)->patchJson('/api/v1/cajas/'.$antigua->public_id.'/estado', ['activa' => false])->assertOk();
    $cotizacion = app(CobroCajas::class)->resumen($admin->negocio, $sucursal, $antigua->fresh())['cotizacion'];
    $this->patchJson('/api/v1/cajas/'.$antigua->public_id.'/estado', ['activa' => true])->assertUnprocessable();
    $this->patchJson('/api/v1/cajas/'.$antigua->public_id.'/estado', ['activa' => true, 'acepto_cobro' => true, 'cotizacion' => $cotizacion])
        ->assertOk()->assertJsonPath('data.es_incluida', false)->assertJsonPath('data.precio_contratado_uf', '0.2500');
    expect($otra->fresh()->es_incluida)->toBeTrue();
    expect(CajaAdicional::whereNull('hasta')->count())->toBe(1);
});

test('an additional register can stop billing and reactivate at the new accepted price without duplicate contracts', function () {
    $admin = User::factory()->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $caja = Caja::factory()->for($sucursal)->create();
    $anterior = CajaAdicional::factory()->for($caja)->create();
    $this->actingAs($admin)->patchJson('/api/v1/cajas/'.$caja->public_id.'/estado', ['activa' => false])->assertOk();
    Tarifa::where('plan', $admin->negocio->plan)->where('concepto', 'caja')->update(['precio_uf' => '0.5000']);
    $payload = ['activa' => true, 'acepto_cobro' => true,
        'cotizacion' => app(CobroCajas::class)->resumen($admin->negocio, $sucursal, $caja->fresh())['cotizacion']];
    $this->patchJson('/api/v1/cajas/'.$caja->public_id.'/estado', $payload)->assertOk()->assertJsonPath('data.precio_contratado_uf', '0.5000');
    $this->patchJson('/api/v1/cajas/'.$caja->public_id.'/estado', $payload)->assertOk();
    expect($anterior->fresh()->precio_uf)->toBe('0.2500');
    expect($anterior->fresh()->hasta)->not->toBeNull();
    expect(CajaAdicional::whereNull('hasta')->count())->toBe(1);
    expect(CajaAdicional::count())->toBe(2);
});

test('creating or reactivating the only register is included without acceptance', function (bool $reactivar) {
    $admin = User::factory()->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $caja = $sucursal->cajas()->sole();
    $this->actingAs($admin)->patchJson('/api/v1/cajas/'.$caja->public_id.'/estado', ['activa' => false])->assertOk();
    if ($reactivar) {
        $this->patchJson('/api/v1/cajas/'.$caja->public_id.'/estado', ['activa' => true])->assertOk()->assertJsonPath('data.es_incluida', true);
    } else {
        $this->postJson('/api/v1/cajas', ['nombre' => 'Nueva incluida', 'sucursal' => $sucursal->public_id])
            ->assertCreated()->assertJsonPath('data.es_incluida', true);
    }
    expect(CajaAdicional::count())->toBe(0);
    expect($sucursal->cajas()->where('activa', true)->where('es_incluida', true)->count())->toBe(1);
})->with([true, false]);

test('missing or stale tariffs and quotations prevent contracting', function (string $escenario, string $campo) {
    $admin = User::factory()->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $cotizacion = app(CobroCajas::class)->resumen($admin->negocio, $sucursal)['cotizacion'];
    if ($escenario === 'precio') {
        Tarifa::where('plan', $admin->negocio->plan)->where('concepto', 'caja')->update(['precio_uf' => '0.5000']);
    } elseif ($escenario === 'sin tarifa') {
        Tarifa::where('plan', $admin->negocio->plan)->where('concepto', 'caja')->update(['precio_uf' => null]);
    } else {
        $cotizacion = str_repeat('0', 64);
    }
    $this->actingAs($admin)->postJson('/api/v1/cajas', ['nombre' => 'No creada', 'sucursal' => $sucursal->public_id,
        'acepto_cobro' => true, 'cotizacion' => $cotizacion])->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(Caja::count())->toBe(1);
    expect(CajaAdicional::count())->toBe(0);
})->with([['precio', 'cotizacion'], ['sin tarifa', 'tarifa'], ['manipulada', 'cotizacion']]);

test('inactive branches reject new registers and activations', function () {
    $admin = User::factory()->admin()->create();
    $sucursal = Sucursal::factory()->for($admin->negocio)->inactiva()->create();
    $caja = $sucursal->cajas()->sole();
    $caja->forceFill(['activa' => false, 'es_incluida' => false])->save();
    $this->actingAs($admin)->postJson('/api/v1/cajas', ['nombre' => 'No creada', 'sucursal' => $sucursal->public_id])
        ->assertUnprocessable()->assertJsonValidationErrors('sucursal');
    $this->patchJson('/api/v1/cajas/'.$caja->public_id.'/estado', ['activa' => true])->assertUnprocessable()->assertJsonValidationErrors('sucursal');
    expect($caja->fresh()->activa)->toBeFalse();
    expect(CajaAdicional::count())->toBe(0);
});

test('quotations prorate the current month in the billing timezone', function () {
    $this->travelTo(now()->setTimezone(config('facturacion.timezone'))->setDate(2026, 10, 24)->startOfDay());
    $admin = User::factory()->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $this->actingAs($admin)->getJson('/api/v1/cajas/cotizacion?sucursal='.$sucursal->public_id)
        ->assertOk()->assertJsonPath('data.este_mes.dias', 8)->assertJsonPath('data.este_mes.monto_uf', '0.0645');
});

test('register filters reject invalid values with 422', function (string $consulta, string $campo) {
    $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/cajas?'.$consulta)
        ->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([['estado=otro', 'estado'], ['page=0', 'page'], ['sucursal=123', 'sucursal'], ['buscar='.str_repeat('a', 101), 'buscar']]);

test('register names can repeat across branches but updates cannot duplicate a name in their own branch', function () {
    $admin = User::factory()->admin()->create();
    $principal = $admin->negocio->sucursales()->sole();
    $norte = Sucursal::factory()->for($admin->negocio)->create();
    $extra = Caja::factory()->for($principal)->create(['nombre' => 'Mostrador']);
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$principal->public_id)->assertOk();
    $this->putJson('/api/v1/cajas/'.$extra->public_id, ['nombre' => 'caja 1'])->assertUnprocessable()->assertJsonValidationErrors('nombre');
    $cotizacion = app(CobroCajas::class)->resumen($admin->negocio, $norte)['cotizacion'];
    $this->postJson('/api/v1/cajas', ['nombre' => 'Mostrador', 'sucursal' => $norte->public_id,
        'acepto_cobro' => true, 'cotizacion' => $cotizacion])->assertCreated();
    $this->getJson('/api/v1/cajas/'.$extra->id)->assertNotFound();
});

test('deactivating and reactivating a branch preserves its included register and starts new accepted extra periods', function () {
    $admin = User::factory()->admin()->create();
    $principal = $admin->negocio->sucursales()->sole();
    $sucursal = Sucursal::factory()->for($admin->negocio)->create();
    $antigua = $sucursal->cajas()->sole();
    $incluida = Caja::factory()->for($sucursal)->create();
    CajaAdicional::factory()->for($incluida)->create();
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$principal->public_id)->assertOk();
    $this->patchJson('/api/v1/cajas/'.$antigua->public_id.'/estado', ['activa' => false])->assertOk();
    $cobro = app(CobroCajas::class)->resumen($admin->negocio, $sucursal, $antigua->fresh());
    $this->patchJson('/api/v1/cajas/'.$antigua->public_id.'/estado', ['activa' => true, 'acepto_cobro' => true, 'cotizacion' => $cobro['cotizacion']])->assertOk();
    $contrato = $antigua->contratacionVigente()->sole();
    $this->patchJson('/api/v1/sucursales/'.$sucursal->public_id.'/estado', ['activa' => false])->assertOk();
    expect(CajaAdicional::whereNull('hasta')->count())->toBe(0);
    expect($contrato->fresh()->hasta)->not->toBeNull();
    Tarifa::where('plan', $admin->negocio->plan)->where('concepto', 'caja')->update(['precio_uf' => '0.5000']);
    $cotizacion = $this->getJson('/api/v1/sucursales/'.$sucursal->public_id.'/cotizacion')->assertOk()
        ->assertJsonPath('data.cajas_adicionales', 1)->json('data.cotizacion');
    $this->patchJson('/api/v1/sucursales/'.$sucursal->public_id.'/estado', ['activa' => true, 'acepto_cobro' => true, 'cotizacion' => $cotizacion])->assertOk();
    expect($incluida->fresh()->es_incluida)->toBeTrue();
    expect($antigua->fresh()->es_incluida)->toBeFalse();
    expect($antigua->contratacionVigente()->sole()->precio_uf)->toBe('0.5000');
    expect(CajaAdicional::whereNull('hasta')->count())->toBe(1);
});
