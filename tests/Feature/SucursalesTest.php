<?php

use App\Enums\Plan;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\SucursalAdicional;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('branch endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([
    ['getJson', '/api/v1/sucursales'], ['getJson', '/api/v1/sucursales/01m5n22fn4rjjt0zc1mwgd9faa'],
    ['postJson', '/api/v1/sucursales'], ['putJson', '/api/v1/sucursales/01m5n22fn4rjjt0zc1mwgd9faa'],
    ['patchJson', '/api/v1/sucursales/01m5n22fn4rjjt0zc1mwgd9faa/estado'],
    ['getJson', '/api/v1/sucursales/01m5n22fn4rjjt0zc1mwgd9faa/cotizacion'],
]);

test('operational users receive 403 when managing branches', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();
    $this->actingAs($usuario)->getJson('/api/v1/sucursales')->assertForbidden();
    $this->postJson('/api/v1/sucursales', ['nombre' => 'Prohibida'])->assertForbidden();
    expect(Sucursal::where('nombre', 'Prohibida')->exists())->toBeFalse();
})->with(['cajero', 'cocina', 'garzon']);

test('reading editing quoting and changing another business branch returns 404', function () {
    $admin = User::factory()->admin()->create();
    $ajena = Sucursal::factory()->create();
    $this->actingAs($admin)->getJson('/api/v1/sucursales/'.$ajena->public_id)->assertNotFound();
    $this->putJson('/api/v1/sucursales/'.$ajena->public_id, ['nombre' => 'Ajena modificada'])->assertNotFound();
    $this->patchJson('/api/v1/sucursales/'.$ajena->public_id.'/estado', ['activa' => false])->assertNotFound();
    $this->getJson('/api/v1/sucursales/'.$ajena->public_id.'/cotizacion')->assertNotFound();
    expect($ajena->fresh()->nombre)->toBe($ajena->nombre);
});

test('the listing is paginated isolated and filtered with the main branch first', function () {
    $admin = User::factory()->admin()->create();
    Sucursal::factory()->for($admin->negocio)->count(16)->create();
    Sucursal::factory()->for($admin->negocio)->inactiva()->create(['nombre' => 'Cerrada', 'direccion' => 'Matta 123']);
    $ajena = Sucursal::factory()->create(['nombre' => 'Ajena']);
    $this->actingAs($admin)->getJson('/api/v1/sucursales')->assertOk()
        ->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 18)->assertJsonPath('data.0.es_principal', true)
        ->assertJsonPath('data.0.cajas_count', 1)->assertJsonMissing(['id' => $ajena->public_id]);
    $this->getJson('/api/v1/sucursales?buscar=Matta&estado=inactiva')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Cerrada');
    $this->getJson('/api/v1/sucursales?buscar='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
});

test('invalid branch filters return 422', function (string $consulta, string $campo) {
    $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/sucursales?'.$consulta)
        ->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([['estado=otro', 'estado'], ['page=0', 'page'], ['page=no', 'page'], ['buscar='.str_repeat('a', 101), 'buscar']]);

test('all plans include the first branch and one register without an extra contract', function (Plan $plan) {
    $admin = User::factory()->for(Negocio::factory()->plan($plan))->admin()->create();
    $this->actingAs($admin)->postJson('/api/v1/sucursales', ['nombre' => ' Primera ', 'es_principal' => false, 'activa' => false, 'negocio_id' => 999])
        ->assertCreated()->assertJsonPath('data.nombre', 'Primera')->assertJsonPath('data.es_principal', true)
        ->assertJsonPath('data.activa', true)->assertJsonPath('data.cajas_count', 1);
    expect(SucursalAdicional::count())->toBe(0);
    expect(Caja::sole()->nombre)->toBe('Caja 1');
    expect(Caja::sole()->negocio_id)->toBe($admin->negocio_id);
})->with(Plan::cases());

test('an extra branch requires acceptance and includes one register for all plans', function (Plan $plan) {
    $admin = User::factory()->for(Negocio::factory()->plan($plan)->conSucursalPrincipal())->admin()->create();
    $this->actingAs($admin);
    $cotizacion = $this->getJson('/api/v1/sucursales')->assertOk()->assertJsonPath('cobro_sucursal.precio_sucursal_uf', '0.2500')->json('cobro_sucursal.cotizacion');
    $this->postJson('/api/v1/sucursales', ['nombre' => 'Norte'])->assertUnprocessable()->assertJsonValidationErrors('acepto_cobro');
    $this->postJson('/api/v1/sucursales', ['nombre' => 'Norte', 'acepto_cobro' => true, 'cotizacion' => $cotizacion])
        ->assertCreated()->assertJsonPath('data.cajas_count', 1)->assertJsonPath('data.es_principal', false);
    $periodo = SucursalAdicional::sole();
    expect($periodo->precio_uf)->toBe('0.2500');
    expect($periodo->aceptado_por_id)->toBe($admin->id);
    expect(CajaAdicional::count())->toBe(0);
    expect(Caja::count())->toBe(2);
})->with(Plan::cases());

test('invalid branch fields return 422 before any branch or register is created', function (array $campos, string $campo) {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->postJson('/api/v1/sucursales', [...['nombre' => 'Nueva'], ...$campos])
        ->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(Sucursal::count())->toBe(1);
    expect(Caja::count())->toBe(1);
})->with([
    [['nombre' => '   '], 'nombre'], [['nombre' => str_repeat('a', 101)], 'nombre'],
    [['direccion' => str_repeat('a', 256)], 'direccion'], [['telefono' => str_repeat('1', 31)], 'telefono'],
    [['nombre' => 'Casa Matriz'], 'nombre'], [['acepto_cobro' => 'yes'], 'acepto_cobro'], [['cotizacion' => 'falsa'], 'cotizacion'],
]);

test('a changed price or forged quote returns 422 without creating a branch', function (bool $cambiarPrecio) {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $cotizacion = $this->getJson('/api/v1/sucursales')->json('cobro_sucursal.cotizacion');
    if ($cambiarPrecio) {
        Tarifa::where('concepto', 'sucursal')->where('plan', $admin->negocio->plan)->update(['precio_uf' => '0.5000']);
    } else {
        $cotizacion = str_repeat('x', 64);
    }
    $this->postJson('/api/v1/sucursales', ['nombre' => 'Norte', 'acepto_cobro' => true, 'cotizacion' => $cotizacion])
        ->assertUnprocessable()->assertJsonValidationErrors('cotizacion');
    expect(Sucursal::count())->toBe(1);
    expect(SucursalAdicional::count())->toBe(0);
})->with([true, false]);

test('editing preserves the included register and the agreed price', function () {
    $admin = User::factory()->admin()->create();
    $sucursal = Sucursal::factory()->for($admin->negocio)->create();
    $periodo = SucursalAdicional::factory()->for($sucursal)->create();
    $caja = $sucursal->cajas()->sole();
    $this->actingAs($admin)->putJson('/api/v1/sucursales/'.$sucursal->public_id, [
        'nombre' => 'Nuevo nombre', 'direccion' => 'Av. Central 123', 'telefono' => null, 'activa' => false, 'es_principal' => true, 'precio_uf' => '0.0000',
    ])->assertOk()->assertJsonPath('data.nombre', 'Nuevo nombre')->assertJsonPath('data.es_principal', false)->assertJsonPath('data.activa', true);
    expect($sucursal->cajas()->sole()->id)->toBe($caja->id);
    expect($periodo->fresh()->precio_uf)->toBe('0.2500');
});

test('the main branch and numeric internal ids are protected', function () {
    $admin = User::factory()->admin()->create();
    $principal = $admin->negocio->sucursales()->sole();
    $this->actingAs($admin)->patchJson('/api/v1/sucursales/'.$principal->public_id.'/estado', ['activa' => false])
        ->assertUnprocessable()->assertJsonValidationErrors(['activa' => 'La sucursal principal no se puede desactivar.']);
    $this->getJson('/api/v1/sucursales/'.$principal->id)->assertNotFound();
    expect($principal->fresh()->activa)->toBeTrue();
});

test('deactivation preserves assignments closes contracts and updates accessible branches', function () {
    $admin = User::factory()->admin()->create();
    $sucursal = Sucursal::factory()->for($admin->negocio)->create();
    $cajero = User::factory()->for($admin->negocio)->cajero()->create();
    $cajero->sucursales()->attach($sucursal);
    $periodo = SucursalAdicional::factory()->for($sucursal)->create();
    $this->actingAs($admin)->patchJson('/api/v1/sucursales/'.$sucursal->public_id.'/estado', ['activa' => false])->assertOk()->assertJsonPath('data.activa', false);
    expect($periodo->fresh()->hasta)->not->toBeNull();
    expect($sucursal->cajas()->count())->toBe(1);
    expect($cajero->sucursales()->whereKey($sucursal->id)->exists())->toBeTrue();
    $this->getJson('/api/v1/me')->assertJsonCount(1, 'data.contexto.sucursales');
});

test('reactivation requires a new acceptance and takes a new price snapshot only once', function () {
    $admin = User::factory()->admin()->create();
    $sucursal = Sucursal::factory()->for($admin->negocio)->inactiva()->create();
    Tarifa::where('concepto', 'sucursal')->where('plan', $admin->negocio->plan)->update(['precio_uf' => '0.4000']);
    $this->actingAs($admin)->patchJson('/api/v1/sucursales/'.$sucursal->public_id.'/estado', ['activa' => true])->assertUnprocessable()->assertJsonValidationErrors('acepto_cobro');
    $cotizacion = $this->getJson('/api/v1/sucursales/'.$sucursal->public_id.'/cotizacion')->assertOk()->json('data.cotizacion');
    $payload = ['activa' => true, 'acepto_cobro' => true, 'cotizacion' => $cotizacion];
    $this->patchJson('/api/v1/sucursales/'.$sucursal->public_id.'/estado', $payload)->assertOk();
    $this->patchJson('/api/v1/sucursales/'.$sucursal->public_id.'/estado', $payload)->assertOk();
    expect(SucursalAdicional::sole()->precio_uf)->toBe('0.4000');
    expect($sucursal->cajas()->count())->toBe(1);
});

test('reactivation quotes and contracts only registers above the one included in that branch', function () {
    $admin = User::factory()->admin()->create();
    $sucursal = Sucursal::factory()->for($admin->negocio)->inactiva()->create();
    Caja::factory()->for($sucursal)->count(2)->create();
    Caja::factory()->for($sucursal)->inactiva()->create();
    $this->actingAs($admin);
    $cotizacion = $this->getJson('/api/v1/sucursales/'.$sucursal->public_id.'/cotizacion')->assertOk()
        ->assertJsonPath('data.cajas_adicionales', 2)->assertJsonPath('data.total_mensual_uf', '0.7500')->json('data.cotizacion');
    $this->patchJson('/api/v1/sucursales/'.$sucursal->public_id.'/estado', ['activa' => true, 'acepto_cobro' => true, 'cotizacion' => $cotizacion])->assertOk();
    expect(CajaAdicional::count())->toBe(2);
    $this->patchJson('/api/v1/sucursales/'.$sucursal->public_id.'/estado', ['activa' => false])->assertOk();
    expect(CajaAdicional::whereNull('hasta')->count())->toBe(0);
});

test('missing tariffs prevent an additional contract without creating partial records', function () {
    $admin = User::factory()->admin()->create();
    Tarifa::where('concepto', 'sucursal')->where('plan', $admin->negocio->plan)->update(['precio_uf' => null]);
    $this->actingAs($admin)->postJson('/api/v1/sucursales', ['nombre' => 'Norte', 'acepto_cobro' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('tarifa');
    expect(Sucursal::count())->toBe(1);
    expect(Caja::count())->toBe(1);
});

test('editing a catalog price only changes contracts accepted afterwards', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $primeraCotizacion = $this->getJson('/api/v1/sucursales')->json('cobro_sucursal.cotizacion');
    $this->postJson('/api/v1/sucursales', ['nombre' => 'Norte', 'acepto_cobro' => true, 'cotizacion' => $primeraCotizacion])->assertCreated();
    $anterior = SucursalAdicional::sole();
    $anterior->tarifa->update(['precio_uf' => '0.5000']);
    $nuevaCotizacion = $this->getJson('/api/v1/sucursales')->json('cobro_sucursal.cotizacion');
    $this->postJson('/api/v1/sucursales', ['nombre' => 'Sur', 'acepto_cobro' => true, 'cotizacion' => $nuevaCotizacion])->assertCreated();
    expect($anterior->fresh()->precio_uf)->toBe('0.2500');
    expect(SucursalAdicional::orderByDesc('id')->firstOrFail()->precio_uf)->toBe('0.5000');
});
