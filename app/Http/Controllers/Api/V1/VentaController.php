<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ventas\AnularVenta;
use App\Actions\Ventas\RegistrarDevolucion;
use App\Actions\Ventas\RegistrarVenta;
use App\Enums\EstadoVenta;
use App\Enums\MedioPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnularVentaRequest;
use App\Http\Requests\RegistrarDevolucionRequest;
use App\Http\Requests\RegistrarVentaRequest;
use App\Http\Resources\ComprobanteResource;
use App\Http\Resources\VentaResource;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Ventas de la sucursal actual: el vendedor ve las suyas y quien tiene `gestionar_cajas`
 * ve todas. Registrar una venta exige un turno de caja abierto; anularla o devolver productos
 * exige además el permiso `anular_ventas`.
 */
class VentaController extends Controller
{
    /** Días de validez del enlace público al comprobante. */
    public const int DIAS_ENLACE = 30;

    /** Relaciones del detalle de una venta. */
    private const array DETALLE = ['vendedor', 'pagos', 'items.modificadores', 'turno', 'anuladaPor', 'devoluciones.usuario', 'devoluciones.items.ventaItem'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'folio' => ['nullable', 'integer', 'min:1'],
            'usuario' => ['nullable', 'string', 'ulid'],
            'medio' => ['nullable', Rule::enum(MedioPago::class)],
            'estado' => ['nullable', Rule::in(['pagada', 'anulada', 'con_devolucion'])],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $veTodas = $this->veTodas($request);
        $ventas = Venta::with(['vendedor', 'pagos'])->withCount('items')
            ->withSum('items as cantidad_vendida', 'cantidad')->withSum('itemsDevueltos as cantidad_devuelta', 'cantidad')->withSum('devoluciones as total_devuelto', 'monto')
            ->when(! $veTodas, fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->when($veTodas && ($filtros['usuario'] ?? null), fn (Builder $query) => $query->whereRelation('vendedor', 'public_id', $filtros['usuario']))
            ->when($filtros['folio'] ?? null, fn (Builder $query, int $folio) => $query->where('numero_sucursal', $folio))
            ->when($filtros['medio'] ?? null, fn (Builder $query, string $medio) => $query->whereRelation('pagos', 'medio', $medio))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => match ($estado) {
                'anulada' => $query->where('estado', EstadoVenta::Anulada),
                'con_devolucion' => $query->has('devoluciones'),
                default => $query->where('estado', EstadoVenta::Pagada)->doesntHave('devoluciones'),
            })
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

        return (new VentaResource($venta->load(self::DETALLE)))->additional(['medios_pago' => $this->mediosPago()]);
    }

    public function store(RegistrarVentaRequest $request, RegistrarVenta $registrar): JsonResponse
    {
        $usuario = $request->user()->fresh();
        abort_unless($usuario->puedeIniciarSesion() && $usuario->can('vender'), 403);

        $venta = $registrar->ejecutar($usuario, $request->validated());

        return (new VentaResource($venta->load(['vendedor', 'pagos', 'items.modificadores'])))->response()->setStatusCode($venta->wasRecentlyCreated ? 201 : 200);
    }

    public function anular(AnularVentaRequest $request, Venta $venta, AnularVenta $anular): VentaResource
    {
        $anular->ejecutar($venta, $this->usuarioQueCorrige($request), $request->validated('motivo'));

        return new VentaResource($venta->refresh()->load(self::DETALLE));
    }

    public function devolver(RegistrarDevolucionRequest $request, Venta $venta, RegistrarDevolucion $devolver): JsonResponse
    {
        $devolucion = $devolver->ejecutar($venta, $this->usuarioQueCorrige($request), $request->validated());

        return (new VentaResource($venta->refresh()->load(self::DETALLE)))->response()->setStatusCode($devolucion->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Comprobante de la venta y un enlace público firmado, válido por 30 días, para compartirlo.
     */
    public function comprobante(Request $request, Venta $venta): JsonResponse
    {
        abort_unless($this->veTodas($request) || $venta->user_id === $request->user()->id, 404);

        $expira = now()->addDays(self::DIAS_ENLACE);
        $firmada = URL::temporarySignedRoute('api.v1.comprobantes.show', $expira, ['venta' => $venta->public_id], absolute: false);

        return response()->json(['data' => [
            'comprobante' => (new ComprobanteResource($venta->load(ComprobanteResource::relaciones())))->resolve($request),
            'enlace' => url('/comprobante/'.$venta->public_id).'?'.parse_url($firmada, PHP_URL_QUERY),
            'expira_at' => $expira->toIso8601String(),
        ]]);
    }

    /**
     * Quien anula o devuelve debe seguir habilitado: se relee de la base de datos.
     */
    private function usuarioQueCorrige(Request $request): User
    {
        $usuario = $request->user()->fresh();
        abort_unless($usuario->puedeIniciarSesion() && $usuario->can('anular_ventas'), 403);

        return $usuario;
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
