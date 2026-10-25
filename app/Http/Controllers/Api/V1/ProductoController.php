<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Negocio;
use App\Models\Producto;
use App\Support\Contexto\NegocioActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    private const array CAMPOS = ['nombre', 'sku', 'codigo_barras', 'descripcion', 'unidad_venta', 'precio', 'costo', 'controla_stock'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'ulid'],
            'marca' => ['nullable', 'string', 'ulid'],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $productos = Producto::with(['categoria', 'marca'])
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $buscar) => $query->where(
                fn (Builder $query) => $query->whereLike('nombre', "%{$buscar}%")
                    ->orWhereLike('sku', "%{$buscar}%")
                    ->orWhereLike('codigo_barras', "%{$buscar}%"),
            ))
            ->when($filtros['categoria'] ?? null, fn (Builder $query, string $categoria) => $query->whereRelation('categoria', 'public_id', $categoria))
            ->when($filtros['marca'] ?? null, fn (Builder $query, string $marca) => $query->whereRelation('marca', 'public_id', $marca))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('activo', $estado === 'activo'))
            ->orderBy('nombre')->orderBy('id')->paginate(15)->withQueryString();

        return ProductoResource::collection($productos)->additional([
            'categorias' => Categoria::orderBy('nombre')->orderBy('id')->get()
                ->map(fn (Categoria $categoria) => $categoria->datosPublicos('nombre', 'activa'))->all(),
            'marcas' => Marca::orderBy('nombre')->orderBy('id')->get()
                ->map(fn (Marca $marca) => $marca->datosPublicos('nombre', 'activa'))->all(),
        ]);
    }

    public function show(Producto $producto): ProductoResource
    {
        return new ProductoResource($producto->load(['categoria', 'marca']));
    }

    public function store(StoreProductoRequest $request, NegocioActual $actual): JsonResponse
    {
        $producto = DB::transaction(function () use ($request, $actual): Producto {
            Negocio::whereKey($actual->id())->lockForUpdate()->firstOrFail();
            $this->exigirPermiso($request);
            [$categoria, $marca] = $this->validarCatalogo($request);
            $this->validarUnicos($request);

            $producto = new Producto($request->safe()->only(self::CAMPOS));
            $producto->categoria()->associate($categoria);
            $producto->marca()->associate($marca);
            $producto->save();

            return $producto->refresh();
        }, 3);

        return (new ProductoResource($producto->load(['categoria', 'marca'])))->response()->setStatusCode(201);
    }

    public function update(UpdateProductoRequest $request, Producto $producto): ProductoResource
    {
        DB::transaction(function () use ($request, $producto): void {
            Negocio::whereKey($producto->negocio_id)->lockForUpdate()->firstOrFail();
            $producto->refresh();
            $this->exigirPermiso($request);
            [$categoria, $marca] = $this->validarCatalogo($request, $producto);
            $this->validarUnicos($request, $producto);

            $producto->fill($request->safe()->only(self::CAMPOS));
            $producto->categoria()->associate($categoria);
            $producto->marca()->associate($marca);
            $producto->save();
        }, 3);

        return new ProductoResource($producto->load(['categoria', 'marca']));
    }

    private function exigirPermiso(Request $request): void
    {
        $editor = $request->user()->fresh();
        abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_catalogo'), 403);
    }

    /**
     * Con el negocio bloqueado, confirma que la categoría y la marca siguen
     * activas (o ya eran las del producto).
     *
     * @return array{0: Categoria, 1: ?Marca}
     */
    private function validarCatalogo(StoreProductoRequest $request, ?Producto $producto = null): array
    {
        $categoria = Categoria::where('public_id', $request->validated('categoria'))->first();

        if (! $categoria || (! $categoria->activa && $categoria->id !== $producto?->categoria_id)) {
            throw ValidationException::withMessages(['categoria' => 'La categoría seleccionada no está disponible.']);
        }

        $marca = $request->validated('marca') ? Marca::where('public_id', $request->validated('marca'))->first() : null;

        if ($request->validated('marca') && (! $marca || (! $marca->activa && $marca->id !== $producto?->marca_id))) {
            throw ValidationException::withMessages(['marca' => 'La marca seleccionada no está disponible.']);
        }

        return [$categoria, $marca];
    }

    private function validarUnicos(StoreProductoRequest $request, ?Producto $producto = null): void
    {
        $mensajes = ['sku' => 'Ya existe un producto con este SKU en tu negocio.', 'codigo_barras' => 'Ya existe un producto con este código de barras en tu negocio.'];

        foreach ($mensajes as $campo => $mensaje) {
            $valor = $request->validated($campo);

            if ($valor !== null && Producto::whereRaw('LOWER('.$campo.') = ?', [Str::lower($valor)])
                ->when($producto, fn ($query) => $query->whereKeyNot($producto->id))->exists()) {
                throw ValidationException::withMessages([$campo => $mensaje]);
            }
        }
    }
}
