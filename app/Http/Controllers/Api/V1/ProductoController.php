<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Modulo;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Categoria;
use App\Models\GrupoModificador;
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
    private const array CAMPOS = ['nombre', 'sku', 'codigo_barras', 'descripcion', 'se_vende', 'es_insumo', 'unidad_venta', 'precio', 'costo', 'controla_stock'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'ulid'],
            'marca' => ['nullable', 'string', 'ulid'],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'tipo' => ['nullable', Rule::in(['venta', 'insumo'])],
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
            ->when($filtros['tipo'] ?? null, fn (Builder $query, string $tipo) => $query->where($tipo === 'venta' ? 'se_vende' : 'es_insumo', true))
            ->orderBy('nombre')->orderBy('id')->paginate(15)->withQueryString();

        return ProductoResource::collection($productos);
    }

    public function opciones(): JsonResponse
    {
        return response()->json([
            'categorias' => Categoria::orderBy('nombre')->orderBy('id')->get()
                ->map(fn (Categoria $categoria) => $categoria->datosPublicos('nombre', 'activa'))->all(),
            'marcas' => Marca::orderBy('nombre')->orderBy('id')->get()
                ->map(fn (Marca $marca) => $marca->datosPublicos('nombre', 'activa'))->all(),
            'grupos_modificadores' => app(NegocioActual::class)->obtener()->tieneModulo(Modulo::Modificadores)
                ? GrupoModificador::orderBy('nombre')->orderBy('id')->get()
                    ->map(fn (GrupoModificador $grupo) => $grupo->datosPublicos('nombre', 'tipo_seleccion', 'activo'))->all()
                : [],
        ]);
    }

    public function show(Producto $producto): ProductoResource
    {
        return new ProductoResource($producto->load(['categoria', 'marca', 'gruposModificadores']));
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
            $this->sincronizarGrupos($producto, $request);

            return $producto->refresh();
        }, 3);

        return (new ProductoResource($producto->load(['categoria', 'marca', 'gruposModificadores'])))->response()->setStatusCode(201);
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
            $this->sincronizarGrupos($producto, $request);
        }, 3);

        return new ProductoResource($producto->load(['categoria', 'marca', 'gruposModificadores']));
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
     * @return array{0: ?Categoria, 1: ?Marca}
     */
    private function validarCatalogo(StoreProductoRequest $request, ?Producto $producto = null): array
    {
        $categoria = $request->validated('categoria') ? Categoria::where('public_id', $request->validated('categoria'))->first() : null;

        if ($request->validated('categoria') && (! $categoria || (! $categoria->activa && $categoria->id !== $producto?->categoria_id))) {
            throw ValidationException::withMessages(['categoria' => 'La categoría seleccionada no está disponible.']);
        }

        $marca = $request->validated('marca') ? Marca::where('public_id', $request->validated('marca'))->first() : null;

        if ($request->validated('marca') && (! $marca || (! $marca->activa && $marca->id !== $producto?->marca_id))) {
            throw ValidationException::withMessages(['marca' => 'La marca seleccionada no está disponible.']);
        }

        return [$categoria, $marca];
    }

    /**
     * Reemplaza los grupos de modificadores del producto por la lista recibida, en ese orden.
     * Con el módulo apagado se ignora y se conservan las asignaciones existentes.
     */
    private function sincronizarGrupos(Producto $producto, StoreProductoRequest $request): void
    {
        $ids = $request->validated('grupos_modificadores');

        if ($ids === null || ! app(NegocioActual::class)->obtener()->tieneModulo(Modulo::Modificadores)) {
            return;
        }

        $grupos = GrupoModificador::whereIn('public_id', $ids)->get()->keyBy('public_id');
        $actuales = $producto->gruposModificadores()->pluck('grupos_modificadores.id')->all();
        $asignaciones = [];

        foreach (array_values($ids) as $orden => $publicId) {
            $grupo = $grupos->get($publicId);

            if ($grupo === null || (! $grupo->activo && ! in_array($grupo->id, $actuales, true))) {
                throw ValidationException::withMessages(['grupos_modificadores' => 'Uno de los grupos de modificadores no está disponible.']);
            }

            $asignaciones[$grupo->id] = ['orden' => $orden];
        }

        $producto->gruposModificadores()->sync($asignaciones);
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
