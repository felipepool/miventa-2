<?php

use App\Actions\Planes\ContratarPlan;
use App\Enums\Plan;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\SucursalAdicional;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CajasInicialesSeeder;
use Database\Seeders\TarifasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('a plan keeps its contracted price after the catalog is edited and seeded again', function (Plan $plan) {
    $admin = User::factory()->for(Negocio::factory()->plan($plan)->conSucursalPrincipal())->admin()->create();
    $tarifa = Tarifa::where('concepto', 'plan')->where('plan', $plan)->sole();
    $tarifa->update(['precio_uf' => '0.7000']);
    $suscripcion = app(ContratarPlan::class)->ejecutar($admin->negocio, $plan, $admin, '0.7000');
    $tarifa->update(['precio_uf' => '0.9000']);
    $this->seed(TarifasSeeder::class);
    expect($suscripcion->fresh()->precio_uf)->toBe('0.7000');
    expect($tarifa->fresh()->precio_uf)->toBe('0.9000');
    expect(app(ContratarPlan::class)->ejecutar($admin->negocio, $plan, $admin, '0.9000')->id)->toBe($suscripcion->id);
    $nuevo = User::factory()->for(Negocio::factory()->plan($plan))->admin()->create();
    expect(app(ContratarPlan::class)->ejecutar($nuevo->negocio, $plan, $nuevo, '0.9000')->precio_uf)->toBe('0.9000');
})->with(Plan::cases());

test('a plan without a configured or accepted current price is not contracted', function (?string $precio) {
    $admin = User::factory()->admin()->create();
    Tarifa::where('concepto', 'plan')->where('plan', $admin->negocio->plan)->update(['precio_uf' => $precio]);
    try {
        app(ContratarPlan::class)->ejecutar($admin->negocio, $admin->negocio->plan, $admin, '0.5000');
        $this->fail('La contratación no debe aceptar una tarifa desconocida o distinta.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('precio_uf');
    }
    expect(Suscripcion::count())->toBe(0);
})->with([null, '0.7500']);

test('changing a plan closes its old contract and snapshots the newly accepted price', function () {
    $admin = User::factory()->admin()->create();
    Tarifa::where('concepto', 'plan')->update(['precio_uf' => '0.5000']);
    $anterior = app(ContratarPlan::class)->ejecutar($admin->negocio, Plan::Retail, $admin, '0.5000');
    $nueva = app(ContratarPlan::class)->ejecutar($admin->negocio, Plan::Comida, $admin, '0.5000');
    expect($anterior->fresh()->hasta)->not->toBeNull();
    expect($nueva->plan)->toBe(Plan::Comida);
    expect($admin->negocio->fresh()->plan)->toBe(Plan::Comida);
});

test('register extra contracts also keep their own price snapshot', function () {
    $sucursal = Sucursal::factory()->create();
    $caja = Caja::factory()->for($sucursal)->create();
    $contrato = CajaAdicional::factory()->for($caja)->create();
    $contrato->tarifa->update(['precio_uf' => '0.6000']);
    expect($contrato->fresh()->precio_uf)->toBe('0.2500');
    $otraCaja = Caja::factory()->for($sucursal)->create();
    expect(CajaAdicional::factory()->for($otraCaja)->create()->precio_uf)->toBe('0.6000');
});

test('contracted prices cannot be overwritten on the model', function () {
    $sucursal = Sucursal::factory()->create();
    $contrato = SucursalAdicional::factory()->for($sucursal)->create();
    $contrato->forceFill(['precio_uf' => '0.0000'])->save();
})->throws(LogicException::class, 'El precio y las condiciones contratadas son inmutables.');

test('branch proration uses the contracted price and stops on the deactivation date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00', 'America/Santiago'));
    $sucursal = Sucursal::factory()->create();
    $contrato = SucursalAdicional::factory()->for($sucursal)->create();
    $contrato->tarifa->update(['precio_uf' => '0.5000']);
    $contrato->update(['hasta' => CarbonImmutable::parse('2026-11-11 12:00:00', 'America/Santiago')]);
    expect($contrato->importeDelMes(CarbonImmutable::parse('2026-11-01', 'America/Santiago')))
        ->toBe(['dias' => 10, 'precio_uf' => '0.2500', 'monto_uf' => '0.0833']);
    expect($contrato->importeDelMes(CarbonImmutable::parse('2026-12-01', 'America/Santiago'))['monto_uf'])->toBe('0.0000');
    expect($contrato->importeDelMes(CarbonImmutable::parse('2026-11-01', 'UTC'))['monto_uf'])->toBe('0.0833');
});

test('contracts started and ended on the same date do not double charge that date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00:00', 'America/Santiago'));
    $contrato = SucursalAdicional::factory()->create();
    $contrato->update(['hasta' => now()->addHours(2)]);
    expect($contrato->importeDelMes(CarbonImmutable::parse('2026-11-01', 'America/Santiago'))['monto_uf'])->toBe('0.0000');
});

test('initial register seeders preserve existing registers and edited tariff prices', function () {
    $sucursal = Sucursal::factory()->create();
    $caja = $sucursal->cajas()->sole();
    $caja->update(['nombre' => 'Mesón']);
    $tarifa = Tarifa::where('concepto', 'caja')->firstOrFail();
    $tarifa->update(['precio_uf' => '0.4000']);
    $this->seed([CajasInicialesSeeder::class, TarifasSeeder::class]);
    $this->seed([CajasInicialesSeeder::class, TarifasSeeder::class]);
    expect($sucursal->cajas()->sole()->nombre)->toBe('Mesón');
    expect($tarifa->fresh()->precio_uf)->toBe('0.4000');
});

test('legacy branches without model events receive exactly one initial register', function () {
    $sucursal = Sucursal::withoutEvents(fn () => Sucursal::factory()->create());
    expect($sucursal->cajas()->count())->toBe(0);
    $this->seed(CajasInicialesSeeder::class);
    $this->seed(CajasInicialesSeeder::class);
    expect($sucursal->cajas()->sole()->nombre)->toBe('Caja 1');
});
