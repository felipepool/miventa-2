<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ventas\RegistrarVenta;
use App\Enums\MedioPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarVentaRequest;
use App\Http\Resources\VentaResource;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Ventas de la sucursal actual: el vendedor ve las suyas y quien tiene `gestionar_cajas`
 * ve todas. Registrar una venta exige un turno de caja abierto.
 */
class VentaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'folio' => ['nullable', 'integer', 'min:1'],
            'usuario' => ['nullable', 'string', 'ulid'],
            'medio' => ['nullable', Rule::enum(MedioPago::class)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $veTodas = $this->veTodas($request);
        $ventas = Venta::with(['vendedor', 'pagos'])->withCount('items')
            ->when(! $veTodas, fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->when($veTodas && ($filtros['usuario'] ?? null), fn (Builder $query) => $query->whereRelation('vendedor', 'public_id', $filtros['usuario']))
            ->when($filtros['folio'] ?? null, fn (Builder $query, int $folio) => $query->where('numero_sucursal', $folio))
            ->when($filtros['medio'] ?? null, fn (Builder $query, string $medio) => $query->whereRelation('pagos', 'medio', $medio))
            ->when($filtros['desde'] ?? null, fn (Builder $query, string $desde) => $query->whereDate('pagada_at', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $query, string $hasta) => $query->whereDate('pagada_at', '<=', $hasta))
            ->orderByDesc('pagada_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return VentaResource::collection($ventas)->additional([
            'usuarios' => $veTodas
                ? User::whereIn('id', Venta::query()->select('user_id'))->orderBy('name')->get()->map(fn (User $usuario) => $usuario->datosPublicos('name'))->all()
                : [],
            'medios_pago' => $this->mediosPago(),
        ]);
    }

    public function show(Request $request, Venta $venta): VentaResource
    {
        abort_unless($this->veTodas($request) || $venta->user_id === $request->user()->id, 404);

        return new VentaResource($venta->load(['vendedor', 'pagos', 'items.modificadores']));
    }

    public function store(RegistrarVentaRequest $request, RegistrarVenta $registrar): JsonResponse
    {
        $usuario = $request->user()->fresh();
        abort_unless($usuario->puedeIniciarSesion() && $usuario->can('vender'), 403);

        $venta = $registrar->ejecutar($usuario, $request->validated());

        return (new VentaResource($venta->load(['vendedor', 'pagos', 'items.modificadores'])))->response()->setStatusCode($venta->wasRecentlyCreated ? 201 : 200);
    }

    private function veTodas(Request $request): bool
    {
        return (bool) $request->user()->can('gestionar_cajas');
    }

    /** @return list<array{value: string, label: string}> */
    private function mediosPago(): array
    {
        return array_map(fn (MedioPago $medio) => ['value' => $medio->value, 'label' => $medio->etiqueta()], MedioPago::cases());
    }
}
