<?php

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function negocioConCaja(array $estado = []): Negocio
{
    return Negocio::factory()->conPlanContratado()->conSucursalPrincipal()->create(['cierre_ciego' => false, ...$estado]);
}

function cajaDe(Negocio $negocio): Caja
{
    return Caja::where('sucursal_id', $negocio->sucursales()->where('es_principal', true)->sole()->id)->orderBy('id')->firstOrFail();
}

function cajeroDe(Negocio $negocio): User
{
    return User::factory()->for($negocio)->cajero()->create();
}

function adminDe(Negocio $negocio): User
{
    return User::factory()->for($negocio)->admin()->create();
}

function abrirTurno(User $usuario, Caja $caja, int $monto = 20000, array $extra = [])
{
    return test()->actingAs($usuario)->postJson('/api/v1/caja/abrir', ['caja' => $caja->public_id, 'monto_inicial' => $monto, ...$extra]);
}

test('cash endpoints return 401 without a session', function (string $metodo, string $ruta) {
    $this->{$metodo}($ruta)->assertUnauthorized();
})->with([
    ['getJson', '/api/v1/caja'], ['postJson', '/api/v1/caja/abrir'], ['postJson', '/api/v1/caja/movimientos'], ['postJson', '/api/v1/caja/cerrar'],
    ['getJson', '/api/v1/turnos'], ['getJson', '/api/v1/turnos/01m5n22fn4rjjt0zc1mwgd9faa'], ['postJson', '/api/v1/turnos/01m5n22fn4rjjt0zc1mwgd9faa/cerrar'],
]);

test('users without the cash permission receive 403', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();
    $this->actingAs($usuario)->getJson('/api/v1/caja')->assertForbidden();
    $this->postJson('/api/v1/caja/abrir', ['caja' => cajaDe($usuario->negocio)->public_id, 'monto_inicial' => 1])->assertForbidden();
    $this->getJson('/api/v1/turnos')->assertForbidden();
    expect(TurnoCaja::count())->toBe(0);
})->with(['cocina', 'garzon']);

test('cash routes require a confirmed branch when several are available', function () {
    $negocio = negocioConCaja();
    Sucursal::factory()->for($negocio)->create();
    $this->actingAs(adminDe($negocio))->getJson('/api/v1/caja')->assertStatus(409)->assertJsonPath('code', 'sucursal_requerida');
});

test('my cash lists the active boxes of the branch and who is using them', function () {
    $negocio = negocioConCaja();
    $caja = cajaDe($negocio);
    $otra = Caja::factory()->create(['sucursal_id' => $caja->sucursal_id, 'nombre' => 'Caja 2']);
    Caja::factory()->inactiva()->create(['sucursal_id' => $caja->sucursal_id]);
    Caja::factory()->create();
    $ocupante = cajeroDe($negocio);
    TurnoCaja::factory()->create(['caja_id' => $otra->id, 'user_id' => $ocupante->id]);
    TurnoCaja::factory()->cerrado()->create(['caja_id' => $caja->id, 'monto_inicial' => 15000, 'monto_esperado' => 15000, 'monto_contado' => 15000]);
    $respuesta = $this->actingAs(cajeroDe($negocio))->getJson('/api/v1/caja')->assertOk()->assertJsonPath('data.turno', null)
        ->assertJsonCount(2, 'data.cajas')->assertJsonPath('data.cajas.0.nombre', 'Caja 1')->assertJsonPath('data.cajas.0.ocupada_por', null)
        ->assertJsonPath('data.cajas.0.monto_sugerido', 15000)->assertJsonPath('data.cajas.1.ocupada_por', $ocupante->name)
        ->assertJsonPath('data.denominaciones.0', 20000)->assertJsonPath('data.puede_ver_esperado', true);
    expect(json_encode($respuesta->json()))->not->toContain('negocio_id')->not->toContain('sucursal_id');
});

test('a shift is opened with the server setting the business, branch and user', function () {
    $negocio = negocioConCaja();
    $caja = cajaDe($negocio);
    $cajero = cajeroDe($negocio);
    $ajeno = Negocio::factory()->create();
    abrirTurno($cajero, $caja, 20000, ['nota' => '  Sencillo  ', 'negocio_id' => $ajeno->id, 'sucursal_id' => 99, 'user_id' => 99, 'numero' => 50])
        ->assertCreated()->assertJsonPath('data.numero', 1)->assertJsonPath('data.monto_inicial', 20000)->assertJsonPath('data.nota_apertura', 'Sencillo')
        ->assertJsonPath('data.caja.nombre', 'Caja 1')->assertJsonPath('data.usuario', $cajero->name)->assertJsonPath('data.cerrada_at', null);
    $turno = TurnoCaja::sole();
    expect($turno->negocio_id)->toBe($negocio->id)->and($turno->sucursal_id)->toBe($caja->sucursal_id)->and($turno->user_id)->toBe($cajero->id);
    $this->getJson('/api/v1/caja')->assertJsonPath('data.turno.numero', 1)->assertJsonPath('data.cajas.0.ocupada_por', $cajero->name);
});

test('shift numbers are consecutive per box', function () {
    $negocio = negocioConCaja();
    $caja = cajaDe($negocio);
    $cajero = cajeroDe($negocio);
    TurnoCaja::factory()->cerrado()->count(2)->sequence(['numero' => 1], ['numero' => 2])->create(['caja_id' => $caja->id]);
    abrirTurno($cajero, $caja)->assertCreated()->assertJsonPath('data.numero', 3);
});

test('invalid openings are rejected', function (Closure $preparar, string $campo) {
    $negocio = negocioConCaja();
    $cajero = cajeroDe($negocio);
    [$caja, $datos] = $preparar($negocio, cajaDe($negocio), $cajero);
    $this->actingAs($cajero)->postJson('/api/v1/caja/abrir', $datos($caja))->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(TurnoCaja::count())->toBe(count(TurnoCaja::pluck('id')));
})->with([
    'unknown box' => [fn ($n, $caja) => [$caja, fn () => ['caja' => '01m5n22fn4rjjt0zc1mwgd9faa', 'monto_inicial' => 1]], 'caja'],
    'not a ulid' => [fn ($n, $caja) => [$caja, fn () => ['caja' => 'x', 'monto_inicial' => 1]], 'caja'],
    'box of another business' => [fn ($n, $caja) => [Caja::factory()->create(), fn (Caja $c) => ['caja' => $c->public_id, 'monto_inicial' => 1]], 'caja'],
    'box of another branch' => [fn ($n, $caja) => [Caja::factory()->create(['sucursal_id' => Sucursal::factory()->for($n)->create()->id]), fn (Caja $c) => ['caja' => $c->public_id, 'monto_inicial' => 1]], 'caja'],
    'inactive box' => [function ($n, $caja) {
        $caja->update(['activa' => false]);

        return [$caja, fn (Caja $c) => ['caja' => $c->public_id, 'monto_inicial' => 1]];
    }, 'caja'],
    'box used by someone else' => [function ($n, $caja) {
        TurnoCaja::factory()->create(['caja_id' => $caja->id, 'user_id' => cajeroDe($n)->id]);

        return [$caja, fn (Caja $c) => ['caja' => $c->public_id, 'monto_inicial' => 1]];
    }, 'caja'],
    'user already has a shift' => [function ($n, $caja, $cajero) {
        TurnoCaja::factory()->create(['caja_id' => Caja::factory()->create(['sucursal_id' => $caja->sucursal_id])->id, 'user_id' => $cajero->id]);

        return [$caja, fn (Caja $c) => ['caja' => $c->public_id, 'monto_inicial' => 1]];
    }, 'caja'],
    'missing amount' => [fn ($n, $caja) => [$caja, fn (Caja $c) => ['caja' => $c->public_id]], 'monto_inicial'],
    'negative amount' => [fn ($n, $caja) => [$caja, fn (Caja $c) => ['caja' => $c->public_id, 'monto_inicial' => -1]], 'monto_inicial'],
    'decimal amount' => [fn ($n, $caja) => [$caja, fn (Caja $c) => ['caja' => $c->public_id, 'monto_inicial' => 10.5]], 'monto_inicial'],
    'huge amount' => [fn ($n, $caja) => [$caja, fn (Caja $c) => ['caja' => $c->public_id, 'monto_inicial' => 100_000_000]], 'monto_inicial'],
    'long note' => [fn ($n, $caja) => [$caja, fn (Caja $c) => ['caja' => $c->public_id, 'monto_inicial' => 1, 'nota' => str_repeat('x', 256)]], 'nota'],
]);

test('income and withdrawals are recorded and change the expected cash', function () {
    $negocio = negocioConCaja();
    $cajero = cajeroDe($negocio);
    abrirTurno($cajero, cajaDe($negocio), 20000)->assertCreated();
    $this->postJson('/api/v1/caja/movimientos', ['tipo' => 'ingreso', 'monto' => 10000, 'motivo' => '  Sencillo  '])->assertCreated()
        ->assertJsonPath('data.tipo', 'ingreso')->assertJsonPath('data.monto', 10000)->assertJsonPath('data.motivo', 'Sencillo')->assertJsonPath('data.usuario', $cajero->name);
    $this->postJson('/api/v1/caja/movimientos', ['tipo' => 'retiro', 'monto' => 5000, 'motivo' => 'Pago proveedor de pan'])->assertCreated();
    $this->getJson('/api/v1/caja')->assertJsonPath('data.turno.ingresos', 10000)->assertJsonPath('data.turno.retiros', 5000)
        ->assertJsonPath('data.turno.monto_esperado', 25000)->assertJsonCount(2, 'data.turno.movimientos');
});

test('a withdrawal cannot exceed the cash in the box', function () {
    $negocio = negocioConCaja();
    abrirTurno(cajeroDe($negocio), cajaDe($negocio), 3000)->assertCreated();
    $this->postJson('/api/v1/caja/movimientos', ['tipo' => 'retiro', 'monto' => 3001, 'motivo' => 'Compra'])->assertUnprocessable()->assertJsonValidationErrors('monto')
        ->assertJsonPath('errors.monto.0', 'No hay suficiente efectivo en la caja para este retiro. Disponible: $3.000.');
    $this->postJson('/api/v1/caja/movimientos', ['tipo' => 'retiro', 'monto' => 3000, 'motivo' => 'Compra'])->assertCreated();
    expect(MovimientoCaja::count())->toBe(1);
});

test('invalid movements are rejected', function (array $datos, string $campo) {
    $negocio = negocioConCaja();
    abrirTurno(cajeroDe($negocio), cajaDe($negocio), 20000)->assertCreated();
    $this->postJson('/api/v1/caja/movimientos', $datos)->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(MovimientoCaja::count())->toBe(0);
})->with([
    'no type' => [['monto' => 1, 'motivo' => 'x'], 'tipo'], 'unknown type' => [['tipo' => 'venta', 'monto' => 1, 'motivo' => 'x'], 'tipo'],
    'zero amount' => [['tipo' => 'ingreso', 'monto' => 0, 'motivo' => 'x'], 'monto'], 'negative amount' => [['tipo' => 'ingreso', 'monto' => -5, 'motivo' => 'x'], 'monto'],
    'decimal amount' => [['tipo' => 'ingreso', 'monto' => 5.5, 'motivo' => 'x'], 'monto'], 'huge amount' => [['tipo' => 'ingreso', 'monto' => 100_000_000, 'motivo' => 'x'], 'monto'],
    'no reason' => [['tipo' => 'ingreso', 'monto' => 5], 'motivo'], 'blank reason' => [['tipo' => 'ingreso', 'monto' => 5, 'motivo' => '  '], 'motivo'],
    'long reason' => [['tipo' => 'ingreso', 'monto' => 5, 'motivo' => str_repeat('x', 151)], 'motivo'], 'array reason' => [['tipo' => 'ingreso', 'monto' => 5, 'motivo' => ['x']], 'motivo'],
]);

test('movements need an open shift and are definitive', function () {
    $negocio = negocioConCaja();
    $this->actingAs(cajeroDe($negocio))->postJson('/api/v1/caja/movimientos', ['tipo' => 'ingreso', 'monto' => 5, 'motivo' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('monto');
    $movimiento = MovimientoCaja::factory()->create();
    $movimiento->motivo = 'Cambiado';
    expect(fn () => $movimiento->save())->toThrow(LogicException::class)->and(fn () => $movimiento->delete())->toThrow(LogicException::class);
});

test('closing counts bills and coins, freezes the figures and cannot be repeated', function () {
    $negocio = negocioConCaja();
    $cajero = cajeroDe($negocio);
    abrirTurno($cajero, cajaDe($negocio), 20000)->assertCreated();
    $this->postJson('/api/v1/caja/movimientos', ['tipo' => 'ingreso', 'monto' => 10000, 'motivo' => 'Sencillo'])->assertCreated();
    $this->postJson('/api/v1/caja/cerrar', ['conteo' => ['20000' => 1, '10000' => 1, '500' => 1, '100' => 0], 'nota' => ' Todo bien '])->assertOk()
        ->assertJsonPath('data.monto_contado', 30500)->assertJsonPath('data.monto_esperado', 30000)->assertJsonPath('data.diferencia', 500)
        ->assertJsonPath('data.nota_cierre', 'Todo bien')->assertJsonPath('data.conteo.0.valor', 20000)->assertJsonPath('data.conteo.0.cantidad', 1)->assertJsonCount(3, 'data.conteo')->assertJsonPath('data.cerrado_por', $cajero->name);
    $turno = TurnoCaja::sole();
    expect($turno->estaAbierto())->toBeFalse()->and($turno->monto_contado)->toBe(30500)->and($turno->diferencia)->toBe(500);
    $this->postJson('/api/v1/caja/cerrar', ['monto_contado' => 1])->assertUnprocessable()->assertJsonValidationErrors('monto_contado');
    $this->postJson('/api/v1/caja/movimientos', ['tipo' => 'ingreso', 'monto' => 5, 'motivo' => 'x'])->assertUnprocessable();
    abrirTurno($cajero, cajaDe($negocio), 1)->assertCreated()->assertJsonPath('data.numero', 2);
});

test('closing accepts a direct total and rejects invalid counts', function (array $datos, ?string $campo) {
    $negocio = negocioConCaja();
    abrirTurno(cajeroDe($negocio), cajaDe($negocio), 20000)->assertCreated();
    $respuesta = $this->postJson('/api/v1/caja/cerrar', $datos);
    $campo === null ? $respuesta->assertOk()->assertJsonPath('data.monto_contado', 18000)->assertJsonPath('data.diferencia', -2000)
        : $respuesta->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(TurnoCaja::sole()->estaAbierto())->toBe($campo !== null);
})->with([
    'direct total' => [['monto_contado' => 18000], null],
    'nothing counted' => [[], 'monto_contado'], 'unknown bill' => [['conteo' => ['7000' => 1]], 'conteo'],
    'negative count' => [['conteo' => ['1000' => -1]], 'conteo.1000'], 'decimal count' => [['conteo' => ['1000' => 1.5]], 'conteo.1000'],
    'huge count' => [['conteo' => ['1000' => 100000]], 'conteo.1000'], 'negative total' => [['monto_contado' => -1], 'monto_contado'],
    'long note' => [['monto_contado' => 1, 'nota' => str_repeat('x', 256)], 'nota'],
]);

test('closing without an open shift is rejected', function () {
    $this->actingAs(cajeroDe(negocioConCaja()))->postJson('/api/v1/caja/cerrar', ['monto_contado' => 1])->assertUnprocessable()->assertJsonValidationErrors('monto_contado');
});

test('the blind close hides the expected cash and the difference from cashiers only', function () {
    $negocio = negocioConCaja(['cierre_ciego' => true]);
    $cajero = cajeroDe($negocio);
    $admin = adminDe($negocio);
    abrirTurno($cajero, cajaDe($negocio), 20000)->assertCreated()->assertJsonMissingPath('data.monto_esperado');
    $this->getJson('/api/v1/caja')->assertJsonPath('data.puede_ver_esperado', false)->assertJsonMissingPath('data.turno.monto_esperado')->assertJsonMissingPath('data.turno.diferencia');
    $this->postJson('/api/v1/caja/movimientos', ['tipo' => 'retiro', 'monto' => 99999, 'motivo' => 'x'])->assertUnprocessable()
        ->assertJsonPath('errors.monto.0', 'No hay suficiente efectivo en la caja para este retiro.');
    $this->postJson('/api/v1/caja/cerrar', ['monto_contado' => 19000])->assertOk()->assertJsonMissingPath('data.monto_esperado')->assertJsonMissingPath('data.diferencia')
        ->assertJsonPath('data.monto_contado', 19000);
    $turno = TurnoCaja::sole();
    $this->getJson('/api/v1/turnos')->assertOk()->assertJsonMissingPath('data.0.monto_esperado')->assertJsonPath('puede_ver_esperado', false);
    $this->getJson('/api/v1/turnos/'.$turno->public_id)->assertOk()->assertJsonMissingPath('data.diferencia');
    $this->actingAs($admin)->getJson('/api/v1/turnos/'.$turno->public_id)->assertOk()->assertJsonPath('data.monto_esperado', 20000)->assertJsonPath('data.diferencia', -1000);
    $this->getJson('/api/v1/turnos')->assertJsonPath('data.0.diferencia', -1000);
});

test('without the blind close everyone with the permission sees the expected cash', function () {
    $negocio = negocioConCaja();
    abrirTurno(cajeroDe($negocio), cajaDe($negocio), 20000)->assertCreated()->assertJsonPath('data.monto_esperado', 20000);
});

test('cashiers see only their own shifts while managers see all of the branch', function () {
    $negocio = negocioConCaja();
    $caja = cajaDe($negocio);
    $cajero = cajeroDe($negocio);
    $otro = cajeroDe($negocio);
    $propio = TurnoCaja::factory()->cerrado()->create(['caja_id' => $caja->id, 'user_id' => $cajero->id]);
    $ajeno = TurnoCaja::factory()->create(['caja_id' => Caja::factory()->create(['sucursal_id' => $caja->sucursal_id])->id, 'user_id' => $otro->id]);
    $deOtraSucursal = TurnoCaja::factory()->create();
    $this->actingAs($cajero)->getJson('/api/v1/turnos')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $propio->public_id)->assertJsonPath('usuarios', []);
    $this->getJson('/api/v1/turnos/'.$propio->public_id)->assertOk();
    $this->getJson('/api/v1/turnos/'.$ajeno->public_id)->assertNotFound();
    $this->getJson('/api/v1/turnos/'.$deOtraSucursal->public_id)->assertNotFound();
    $this->actingAs(adminDe($negocio))->getJson('/api/v1/turnos')->assertOk()->assertJsonCount(2, 'data')->assertJsonCount(2, 'cajas')->assertJsonCount(2, 'usuarios');
    $this->getJson('/api/v1/turnos/'.$ajeno->public_id)->assertOk()->assertJsonPath('data.usuario', $otro->name);
    $this->getJson('/api/v1/turnos/'.$deOtraSucursal->public_id)->assertNotFound();
});

test('shifts can be filtered by box, user, state and dates', function () {
    $negocio = negocioConCaja();
    $caja = cajaDe($negocio);
    $otraCaja = Caja::factory()->create(['sucursal_id' => $caja->sucursal_id]);
    $ana = cajeroDe($negocio);
    $luis = cajeroDe($negocio);
    TurnoCaja::factory()->cerrado()->create(['caja_id' => $caja->id, 'user_id' => $ana->id, 'abierta_at' => '2026-10-01 09:00:00']);
    TurnoCaja::factory()->create(['caja_id' => $otraCaja->id, 'user_id' => $luis->id, 'abierta_at' => '2026-10-20 09:00:00']);
    $this->actingAs(adminDe($negocio));
    $this->getJson('/api/v1/turnos?caja='.$otraCaja->public_id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.usuario', $luis->name);
    $this->getJson('/api/v1/turnos?usuario='.$ana->public_id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.usuario', $ana->name);
    $this->getJson('/api/v1/turnos?estado=abierto')->assertJsonCount(1, 'data')->assertJsonPath('data.0.cerrada_at', null);
    $this->getJson('/api/v1/turnos?estado=cerrado')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/turnos?desde=2026-10-10')->assertJsonCount(1, 'data')->assertJsonPath('data.0.usuario', $luis->name);
    $this->getJson('/api/v1/turnos?hasta=2026-10-10')->assertJsonCount(1, 'data')->assertJsonPath('data.0.usuario', $ana->name);
    $this->getJson('/api/v1/turnos?estado=otro')->assertUnprocessable();
    $this->getJson('/api/v1/turnos?desde=2026-10-10&hasta=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('hasta');
});

test('the shift history shows five shifts per page unless another allowed size is requested', function () {
    $negocio = negocioConCaja();
    $caja = cajaDe($negocio);
    $cajero = cajeroDe($negocio);
    foreach (range(1, 6) as $numero) {
        TurnoCaja::factory()->cerrado()->create(['caja_id' => $caja->id, 'user_id' => $cajero->id, 'numero' => $numero]);
    }
    $this->actingAs(adminDe($negocio));
    $this->getJson('/api/v1/turnos')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.per_page', 5)->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/v1/turnos?per_page=10')->assertOk()->assertJsonCount(6, 'data')->assertJsonPath('meta.per_page', 10);
});

test('the shift history rejects page sizes outside the allowed list', function (string $porPagina) {
    $this->actingAs(adminDe(negocioConCaja()))->getJson('/api/v1/turnos?per_page='.$porPagina)->assertUnprocessable()->assertJsonValidationErrors('per_page');
})->with(['15', '0', '1000', 'todos']);

test('a manager can close another persons shift but a cashier cannot', function () {
    $negocio = negocioConCaja();
    $cajero = cajeroDe($negocio);
    $deOtroNegocio = TurnoCaja::factory()->create();
    abrirTurno($cajero, cajaDe($negocio), 20000)->assertCreated();
    $turno = TurnoCaja::sole();
    $this->postJson('/api/v1/turnos/'.$turno->public_id.'/cerrar', ['monto_contado' => 20000])->assertForbidden();
    expect($turno->fresh()->estaAbierto())->toBeTrue();
    $admin = adminDe($negocio);
    $this->actingAs($admin)->postJson('/api/v1/turnos/'.$turno->public_id.'/cerrar', ['monto_contado' => 19000, 'nota' => 'Cierre administrativo'])->assertOk()
        ->assertJsonPath('data.cerrado_por', $admin->name)->assertJsonPath('data.usuario', $cajero->name)->assertJsonPath('data.diferencia', -1000);
    $this->postJson('/api/v1/turnos/'.$turno->public_id.'/cerrar', ['monto_contado' => 1])->assertUnprocessable();
    $this->postJson('/api/v1/turnos/'.$deOtroNegocio->public_id.'/cerrar', ['monto_contado' => 1])->assertNotFound();
});

test('the cash cannot be operated when the business is read only', function () {
    $negocio = Negocio::factory()->conSucursalPrincipal()->create();
    $negocio->forceFill(['prueba_desde' => now()->subDays(30), 'prueba_hasta' => now()->subDays(16)])->save();
    $cajero = cajeroDe($negocio);
    $this->actingAs($cajero)->getJson('/api/v1/caja')->assertOk();
    abrirTurno($cajero, cajaDe($negocio))->assertForbidden()->assertJsonPath('code', 'negocio_solo_lectura');
    expect(TurnoCaja::count())->toBe(0);
});
