<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Caja\AbrirTurno;
use App\Actions\Caja\CerrarTurno;
use App\Actions\Caja\RegistrarMovimientoCaja;
use App\Enums\TipoMovimientoCaja;
use App\Http\Controllers\Controller;
use App\Http\Requests\AbrirTurnoRequest;
use App\Http\Requests\CerrarTurnoRequest;
use App\Http\Requests\RegistrarMovimientoCajaRequest;
use App\Http\Resources\MovimientoCajaResource;
use App\Http\Resources\TurnoCajaResource;
use App\Models\Caja;
use App\Models\TurnoCaja;
use App\Support\Caja\Denominaciones;
use App\Support\Contexto\SucursalActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Caja del usuario autenticado: su turno abierto, apertura, movimientos y cierre.
 */
class MiCajaController extends Controller
{
    public function show(Request $request, SucursalActual $actual): JsonResponse
    {
        $turno = $this->turnoAbiertoDe($request)?->load(['caja', 'usuario', 'movimientos.usuario']);
        $cajas = Caja::where('sucursal_id', $actual->id())->where('activa', true)->orderBy('nombre')->orderBy('id')->get();
        $ocupadas = TurnoCaja::abiertos()->with('usuario')->whereIn('caja_id', $cajas->modelKeys())->get()->keyBy('caja_id');

        return response()->json(['data' => [
            'turno' => $turno ? (new TurnoCajaResource($turno))->resolve($request) : null,
            'cajas' => $cajas->map(fn (Caja $caja) => [
                ...$caja->datosPublicos('nombre'),
                'ocupada_por' => $ocupadas->get($caja->id)?->usuario->name,
                'monto_sugerido' => TurnoCaja::whereBelongsTo($caja)->whereNotNull('cerrada_at')->orderByDesc('numero')->value('monto_contado'),
            ])->all(),
            'denominaciones' => Denominaciones::VALORES,
            'puede_ver_esperado' => TurnoCajaResource::puedeVerEsperado($request),
        ]]);
    }

    public function abrir(AbrirTurnoRequest $request, AbrirTurno $abrir, SucursalActual $actual): JsonResponse
    {
        $this->exigirPermiso($request);
        $turno = $abrir->ejecutar($request->user(), $actual->obtener(), $request->validated('caja'), $request->validated('monto_inicial'), $request->validated('nota'));

        return (new TurnoCajaResource($turno->load(['caja', 'usuario'])))->response()->setStatusCode(201);
    }

    public function registrarMovimiento(RegistrarMovimientoCajaRequest $request, RegistrarMovimientoCaja $registrar): JsonResponse
    {
        $this->exigirPermiso($request);
        $movimiento = $registrar->ejecutar(
            $request->user(),
            TipoMovimientoCaja::from($request->validated('tipo')),
            $request->validated('monto'),
            $request->validated('motivo'),
            TurnoCajaResource::puedeVerEsperado($request),
        );

        return (new MovimientoCajaResource($movimiento->load('usuario')))->response()->setStatusCode(201);
    }

    public function cerrar(CerrarTurnoRequest $request, CerrarTurno $cerrar): TurnoCajaResource
    {
        $this->exigirPermiso($request);
        $turno = $this->turnoAbiertoDe($request)
            ?? throw ValidationException::withMessages(['monto_contado' => 'No tienes una caja abierta.']);
        $cerrado = $cerrar->ejecutar($turno, $request->user(), $request->validated('conteo'), $request->validated('monto_contado'), $request->validated('nota'));

        return new TurnoCajaResource($cerrado->load(['caja', 'usuario', 'cerradoPor', 'movimientos.usuario']));
    }

    private function turnoAbiertoDe(Request $request): ?TurnoCaja
    {
        return TurnoCaja::abiertos()->where('user_id', $request->user()->id)->first();
    }

    private function exigirPermiso(Request $request): void
    {
        $usuario = $request->user()->fresh();
        abort_unless($usuario->puedeIniciarSesion() && $usuario->can('operar_caja'), 403);
    }
}
