<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMarcaRequest;
use App\Http\Requests\UpdateMarcaRequest;
use App\Http\Resources\MarcaResource;
use App\Models\Marca;
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

class MarcaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['activa', 'inactiva'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $marcas = Marca::query()->withCount('productos')
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $buscar) => $query->whereLike('nombre', "%{$buscar}%"))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('activa', $estado === 'activa'))
            ->orderBy('nombre')->orderBy('id')->paginate(15)->withQueryString();

        return MarcaResource::collection($marcas);
    }

    public function show(Marca $marca): MarcaResource
    {
        return new MarcaResource($marca->loadCount('productos'));
    }

    public function store(StoreMarcaRequest $request, NegocioActual $actual): JsonResponse
    {
        $marca = DB::transaction(function () use ($request, $actual): Marca {
            Negocio::whereKey($actual->id())->lockForUpdate()->firstOrFail();
            $this->exigirPermiso($request);
            $this->validarNombre($request->validated('nombre'));

            return Marca::create($request->safe()->only(['nombre']))->refresh();
        }, 3);

        return (new MarcaResource($marca->loadCount('productos')))->response()->setStatusCode(201);
    }

    public function update(UpdateMarcaRequest $request, Marca $marca): MarcaResource
    {
        DB::transaction(function () use ($request, $marca): void {
            Negocio::whereKey($marca->negocio_id)->lockForUpdate()->firstOrFail();
            $marca->refresh();
            $this->exigirPermiso($request);
            $this->validarNombre($request->validated('nombre'), $marca);
            $marca->update($request->safe()->only(['nombre']));
        }, 3);

        return new MarcaResource($marca->loadCount('productos'));
    }

    private function exigirPermiso(Request $request): void
    {
        $editor = $request->user()->fresh();
        abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_catalogo'), 403);
    }

    private function validarNombre(string $nombre, ?Marca $marca = null): void
    {
        if (Marca::whereRaw('LOWER(nombre) = ?', [Str::lower($nombre)])
            ->when($marca, fn ($query) => $query->whereKeyNot($marca->id))->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una marca con este nombre en tu negocio.']);
        }
    }
}
