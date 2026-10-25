<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use App\Models\Negocio;
use App\Support\Contexto\NegocioActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoriaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['activa', 'inactiva'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $categorias = Categoria::query()->withCount('productos')
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $buscar) => $query->whereLike('nombre', "%{$buscar}%"))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('activa', $estado === 'activa'))
            ->orderBy('nombre')->orderBy('id')->paginate(15)->withQueryString();

        return CategoriaResource::collection($categorias);
    }

    public function show(Categoria $categoria): CategoriaResource
    {
        return new CategoriaResource($categoria->loadCount('productos'));
    }

    public function store(StoreCategoriaRequest $request, NegocioActual $actual): JsonResponse
    {
        $categoria = DB::transaction(function () use ($request, $actual): Categoria {
            Negocio::whereKey($actual->id())->lockForUpdate()->firstOrFail();
            $this->exigirPermiso($request);
            $this->validarNombre($request->validated('nombre'));

            return Categoria::create($request->safe()->only(['nombre']))->refresh();
        }, 3);

        return (new CategoriaResource($categoria->loadCount('productos')))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria): CategoriaResource
    {
        DB::transaction(function () use ($request, $categoria): void {
            Negocio::whereKey($categoria->negocio_id)->lockForUpdate()->firstOrFail();
            $categoria->refresh();
            $this->exigirPermiso($request);
            $this->validarNombre($request->validated('nombre'), $categoria);
            $categoria->update($request->safe()->only(['nombre']));
        }, 3);

        return new CategoriaResource($categoria->loadCount('productos'));
    }

    private function exigirPermiso(Request $request): void
    {
        $editor = $request->user()->fresh();
        abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_catalogo'), 403);
    }

    private function validarNombre(string $nombre, ?Categoria $categoria = null): void
    {
        if (Categoria::whereRaw('LOWER(nombre) = ?', [Str::lower($nombre)])
            ->when($categoria, fn ($query) => $query->whereKeyNot($categoria->id))->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una categoría con este nombre en tu negocio.']);
        }
    }
}
