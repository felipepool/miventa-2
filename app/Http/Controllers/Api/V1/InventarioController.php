<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Inventario\RegistrarMovimientoStock;
use App\Enums\UnidadVenta;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarMovimientoStockRequest;
use App\Http\Resources\InventarioResource;
use App\Http\Resources\MovimientoStockResource;
use App\Models\Categoria;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Support\Contexto\SucursalActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inventario de la sucursal actual: existencias, movimientos y stock mínimo.
 */
class InventarioController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'ulid'],
            'stock' => ['nullable', Rule::in(['bajo'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $productos = Producto::with('stock')->where('controla_stock', true)
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $buscar) => $query->where(
                fn (Builder $query) => $query->whereLike('nombre', "%{$buscar}%")
                    ->orWhereLike('sku', "%{$buscar}%")
                    ->orWhereLike('codigo_barras', "%{$buscar}%"),
            ))
            ->when($filtros['categoria'] ?? null, fn (Builder $query, string $categoria) => $query->whereRelation('categoria', 'public_id', $categoria))
            ->when($filtros['stock'] ?? null, fn (Builder $query) => $query->whereHas(
                'stock',
                fn (Builder $stock) => $stock->where('stock_minimo', '>', 0)->whereColumn('cantidad', '<=', 'stock_minimo'),
            ))
            ->orderBy('nombre')->orderBy('id')->paginate(15)->withQueryString();

        return InventarioResource::collection($productos)->additional([
            'categorias' => Categoria::orderBy('nombre')->orderBy('id')->get()
                ->map(fn (Categoria $categoria) => $categoria->datosPublicos('nombre', 'activa'))->all(),
        ]);
    }

    public function movimientos(Producto $producto): AnonymousResourceCollection
    {
        $movimientos = MovimientoStock::with('usuario')->where('producto_id', $producto->id)
            ->orderByDesc('id')->paginate(15);

        return MovimientoStockResource::collection($movimientos);
    }

    public function registrarMovimiento(RegistrarMovimientoStockRequest $request, Producto $producto, SucursalActual $actual, RegistrarMovimientoStock $registrar): JsonResponse
    {
        $this->exigirPermiso($request);
        $this->exigirControlDeStock($producto);

        $movimiento = $registrar->ejecutar(
            $producto,
            $actual->obtener(),
            $request->user(),
            $request->validated('tipo'),
            (string) $request->validated('cantidad'),
            $request->validated('motivo'),
        );

        return (new MovimientoStockResource($movimiento->load('usuario')))->response()->setStatusCode(201);
    }

    public function fijarMinimo(Request $request, Producto $producto, SucursalActual $actual, RegistrarMovimientoStock $registrar): InventarioResource
    {
        $decimales = $producto->unidad_venta === UnidadVenta::Unidad ? 'decimal:0' : 'decimal:0,3';
        $minimo = $request->validate([
            'stock_minimo' => ['required', 'numeric', $decimales, 'min:0', 'max:'.RegistrarMovimientoStockRequest::CANTIDAD_MAXIMA],
        ])['stock_minimo'];
        $this->exigirPermiso($request);
        $this->exigirControlDeStock($producto);

        $registrar->fijarMinimo($producto, $actual->obtener(), (string) $minimo);

        return new InventarioResource($producto->load('stock'));
    }

    private function exigirPermiso(Request $request): void
    {
        $editor = $request->user()->fresh();
        abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_inventario'), 403);
    }

    private function exigirControlDeStock(Producto $producto): void
    {
        if (! $producto->controla_stock) {
            throw ValidationException::withMessages(['producto' => 'Este producto no controla stock.']);
        }
    }
}
