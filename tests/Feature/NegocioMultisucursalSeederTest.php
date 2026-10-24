<?php

use App\Actions\Planes\ContratarPlan;
use App\Actions\Planes\RecursosContratacion;
use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\SucursalAdicional;
use App\Models\Tarifa;
use App\Models\User;
use Database\Seeders\NegocioMultisucursalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.admin.password' => 'demo-test-password']);
});

test('the seeded admin has three branches with one included register each and free trial extras', function () {
    $this->seed(NegocioMultisucursalSeeder::class);

    $admin = User::where('username', 'admin')->sole();
    setPermissionsTeamId($admin->negocio_id);

    expect($admin->sucursalesAccesibles()->pluck('nombre')->all())
        ->toBe(['Casa Matriz', 'Sucursal Norte', 'Sucursal Sur']);
    expect(Caja::withoutGlobalScopes()->count())->toBe(6);
    expect(Caja::withoutGlobalScopes()->where('es_incluida', true)->count())->toBe(3);
    expect(CajaAdicional::withoutGlobalScopes()->count())->toBe(0);
    expect(SucursalAdicional::withoutGlobalScopes()->count())->toBe(0);
    expect(Hash::check('demo-test-password', $admin->password))->toBeTrue();
});

test('repeating the seed preserves data and contracted prices without duplicates', function () {
    $this->seed(NegocioMultisucursalSeeder::class);
    $admin = User::where('username', 'admin')->sole();
    Tarifa::where('concepto', 'plan')->where('plan', Plan::Mixto)->update(['precio_uf' => '1.0000']);
    app(ContratarPlan::class)->ejecutar($admin->negocio, Plan::Mixto, $admin, '1.0000');
    app(RecursosContratacion::class)->contratar($admin->negocio, Plan::Mixto, $admin);
    $admin->forceFill(['name' => 'Nombre editado', 'password' => 'changed-password'])->save();
    Tarifa::where('plan', Plan::Mixto)->where('concepto', ConceptoTarifa::Sucursal)->update(['precio_uf' => '0.5000']);
    Tarifa::where('plan', Plan::Mixto)->where('concepto', ConceptoTarifa::Caja)->update(['precio_uf' => '0.5000']);

    $this->seed(NegocioMultisucursalSeeder::class);

    expect(Negocio::count())->toBe(1);
    expect(User::count())->toBe(1);
    expect(Sucursal::withoutGlobalScopes()->count())->toBe(3);
    expect(Caja::withoutGlobalScopes()->count())->toBe(6);
    expect(CajaAdicional::withoutGlobalScopes()->pluck('precio_uf')->all())->toBe(['0.2500', '0.2500', '0.2500']);
    expect(SucursalAdicional::withoutGlobalScopes()->pluck('precio_uf')->all())->toBe(['0.2500', '0.2500']);
    expect($admin->fresh()->name)->toBe('Nombre editado');
    expect(Hash::check('changed-password', $admin->fresh()->password))->toBeTrue();
});

test('demo seeding is rejected before creating data when configuration is unsafe', function (bool $production) {
    if ($production) {
        app()->instance('env', 'production');
    } else {
        config(['app.admin.password' => null]);
    }

    expect(fn () => (new NegocioMultisucursalSeeder)->run())->toThrow(RuntimeException::class);
    expect(Negocio::count())->toBe(0);
    expect(User::count())->toBe(0);
})->with([true, false]);
