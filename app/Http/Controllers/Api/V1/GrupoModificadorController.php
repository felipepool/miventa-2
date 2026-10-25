<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TipoSeleccion;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGrupoModificadorRequest;
use App\Http\Requests\UpdateGrupoModificadorRequest;
use App\Http\Resources\GrupoModificadorResource;
use App\Models\GrupoModificador;
use App\Models\Negocio;
use App\Support\Contexto\NegocioActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GrupoModificadorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $grupos = GrupoModificador::with('modificadores')->withCount('productos')
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $buscar) => $query->where(
                fn (Builder $query) => $query->whereLike('nombre', "%{$buscar}%")->orWhereLike('referencia', "%{$buscar}%"),
            ))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('activo', $estado === 'activo'))
            ->orderBy('nombre')->orderBy('id')->paginate(15)->withQueryString();

        return GrupoModificadorResource::collection($grupos);
    }

    public function show(GrupoModificador $grupoModificador): GrupoModificadorResource
    {
        return new GrupoModificadorResource($grupoModificador->load('modificadores')->loadCount('productos'));
    }

    public function store(StoreGrupoModificadorRequest $request, NegocioActual $actual): JsonResponse
    {
        $grupo = DB::transaction(function () use ($request, $actual): GrupoModificador {
            Negocio::whereKey($actual->id())->lockForUpdate()->firstOrFail();
            $this->exigirPermiso($request);

            return $this->guardar(new GrupoModificador, $request->validated());
        }, 3);

        return (new GrupoModificadorResource($grupo->load('modificadores')->loadCount('productos')))->response()->setStatusCode(201);
    }

    public function update(UpdateGrupoModificadorRequest $request, GrupoModificador $grupoModificador): GrupoModificadorResource
    {
        DB::transaction(function () use ($request, $grupoModificador): void {
            Negocio::whereKey($grupoModificador->negocio_id)->lockForUpdate()->firstOrFail();
            $grupoModificador->refresh();
            $this->exigirPermiso($request);
            $this->guardar($grupoModificador, $request->validated());
        }, 3);

        return new GrupoModificadorResource($grupoModificador->load('modificadores')->loadCount('productos'));
    }

    private function exigirPermiso(Request $request): void
    {
        $editor = $request->user()->fresh();
        abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_catalogo'), 403);
    }

    /**
     * Guarda el grupo con sus modificadores. Los modificadores que ya no llegan
     * se desactivan (no se borran) para conservar su historial.
     *
     * @param  array<string, mixed>  $datos
     */
    private function guardar(GrupoModificador $grupo, array $datos): GrupoModificador
    {
        $tipo = TipoSeleccion::from($datos['tipo_seleccion']);
        $esUnica = $tipo === TipoSeleccion::Unica;
        $grupo->fill([
            'nombre' => $datos['nombre'],
            'referencia' => $datos['referencia'] ?? null,
            'tipo_seleccion' => $tipo,
            'minimo' => $datos['minimo'],
            'maximo' => $esUnica ? 1 : ($datos['maximo'] ?? null),
            'cantidad_gratis' => $esUnica ? null : ($datos['cantidad_gratis'] ?? null),
            'precio_extra' => $esUnica ? 0 : $datos['precio_extra'],
        ])->save();

        $existentes = $grupo->modificadores()->get()->keyBy('public_id');
        $conservados = [];

        $modificadores = $datos['modificadores'];
        ksort($modificadores);

        foreach (array_values($modificadores) as $indice => $modificador) {
            $atributos = [
                'nombre' => $modificador['nombre'],
                'precio_adicional' => $modificador['precio_adicional'],
                'cantidad_maxima' => $tipo === TipoSeleccion::Cantidad ? ($modificador['cantidad_maxima'] ?? null) : null,
                'orden' => $indice,
                'activo' => $modificador['activo'],
            ];

            if (($modificador['id'] ?? null) === null) {
                $conservados[] = $grupo->modificadores()->create($atributos)->id;

                continue;
            }

            $actual = $existentes->get($modificador['id'])
                ?? throw ValidationException::withMessages(["modificadores.$indice.id" => 'El modificador no está disponible.']);
            $actual->update($atributos);
            $conservados[] = $actual->id;
        }

        $grupo->modificadores()->whereNotIn('id', $conservados)->update(['activo' => false]);

        return $grupo->refresh();
    }
}
