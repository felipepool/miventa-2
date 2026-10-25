<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Caja\CerrarTurno;
use App\Http\Controllers\Controller;
use App\Http\Requests\CerrarTurnoRequest;
use App\Http\Resources\TurnoCajaResource;
use App\Models\Caja;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Support\Caja\Denominaciones;
use App\Support\Contexto\SucursalActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Historial de turnos de la sucursal actual: el cajero ve los suyos y quien
 * tiene `gestionar_cajas` ve todos y puede cerrar los de otras personas.
 */
class TurnoCajaController extends Controller
{
    public function index(Request $request, SucursalActual $actual): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'caja' => ['nullable', 'string', 'ulid'],
            'usuario' => ['nullable', 'string', 'ulid'],
            'estado' => ['nullable', Rule::in(['abierto', 'cerrado'])],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $veTodos = $this->veTodos($request);
        $turnos = $this->consultaConTotales()->with(['caja', 'usuario', 'cerradoPor'])
            ->when(! $veTodos, fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->when($veTodos && ($filtros['usuario'] ?? null), fn (Builder $query) => $query->whereRelation('usuario', 'public_id', $filtros['usuario']))
            ->when($filtros['caja'] ?? null, fn (Builder $query, string $caja) => $query->whereRelation('caja', 'public_id', $caja))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $estado === 'abierto' ? $query->whereNull('cerrada_at') : $query->whereNotNull('cerrada_at'))
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $desde) => $query->whereDate('abierta_at', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $hasta) => $query->whereDate('abierta_at', '<=', $hasta))
            ->orderByDesc('abierta_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return TurnoCajaResource::collection($turnos)->additional([
            'cajas' => Caja::where('sucursal_id', $actual->id())->orderBy('nombre')->orderBy('id')->get()->map(fn (Caja $caja) => $caja->datosPublicos('nombre'))->all(),
            'usuarios' => $veTodos
                ? User::whereIn('id', TurnoCaja::query()->select('user_id'))->orderBy('name')->get()->map(fn (User $usuario) => $usuario->datosPublicos('name'))->all()
                : [],
            'puede_ver_esperado' => TurnoCajaResource::puedeVerEsperado($request),
        ]);
    }

    public function show(Request $request, TurnoCaja $turnoCaja): TurnoCajaResource
    {
        abort_unless($this->veTodos($request) || $turnoCaja->user_id === $request->user()->id, 404);

        return (new TurnoCajaResource($turnoCaja->load(['caja', 'usuario', 'cerradoPor', 'movimientos.usuario'])))
            ->additional(['denominaciones' => Denominaciones::VALORES]);
    }

    public function cerrar(CerrarTurnoRequest $request, TurnoCaja $turnoCaja, CerrarTurno $cerrar): TurnoCajaResource
    {
        $usuario = $request->user()->fresh();
        abort_unless($usuario->puedeIniciarSesion() && $usuario->can('gestionar_cajas'), 403);

        if (! $turnoCaja->estaAbierto()) {
            throw ValidationException::withMessages(['monto_contado' => 'Esta caja ya fue cerrada.']);
        }

        $cerrado = $cerrar->ejecutar($turnoCaja, $usuario, $request->validated('conteo'), $request->validated('monto_contado'), $request->validated('nota'));

        return new TurnoCajaResource($cerrado->load(['caja', 'usuario', 'cerradoPor', 'movimientos.usuario']));
    }

    private function veTodos(Request $request): bool
    {
        return (bool) $request->user()->can('gestionar_cajas');
    }

    /** @return Builder<TurnoCaja> */
    private function consultaConTotales(): Builder
    {
        return TurnoCaja::query()
            ->withSum(['movimientos as ingresos' => fn (Builder $query) => $query->where('tipo', 'ingreso')], 'monto')
            ->withSum(['movimientos as retiros' => fn (Builder $query) => $query->where('tipo', 'retiro')], 'monto')
            ->withSum(['pagos as ventas_efectivo' => fn (Builder $query) => $query->where('pagos.medio', 'efectivo')->where('ventas.estado', 'pagada')], 'monto');
    }
}
