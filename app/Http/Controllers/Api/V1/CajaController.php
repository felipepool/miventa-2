<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Planes\CobroCajas;
use App\Enums\ConceptoTarifa;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCajaRequest;
use App\Http\Requests\UpdateCajaRequest;
use App\Http\Resources\CajaResource;
use App\Models\Caja;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\Tarifa;
use App\Support\Contexto\NegocioActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CajaController extends Controller
{
    public function index(Request $request, NegocioActual $actual): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'sucursal' => ['nullable', 'string', 'ulid'],
            'estado' => ['nullable', Rule::in(['activa', 'inactiva'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $cajas = Caja::with(['sucursal', 'contratacionVigente'])
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $buscar) => $query->whereLike('nombre', "%{$buscar}%"))
            ->when($filtros['sucursal'] ?? null, fn (Builder $query, string $sucursal) => $query->whereRelation('sucursal', 'public_id', $sucursal))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('activa', $estado === 'activa'))
            ->orderByDesc(Sucursal::select('es_principal')->whereColumn('sucursales.id', 'cajas.sucursal_id'))
            ->orderBy(Sucursal::select('nombre')->whereColumn('sucursales.id', 'cajas.sucursal_id'))
            ->orderBy('sucursal_id')->orderByDesc('es_incluida')->orderBy('nombre')->orderBy('id')
            ->paginate(15)->withQueryString();

        return CajaResource::collection($cajas)->additional([
            'sucursales' => Sucursal::orderByDesc('es_principal')->orderBy('nombre')->orderBy('id')->get()
                ->map(fn (Sucursal $sucursal) => $sucursal->datosPublicos('nombre', 'activa', 'es_principal'))->all(),
            'precio_caja_uf' => Tarifa::where('plan', $actual->obtener()->plan)->where('concepto', ConceptoTarifa::Caja)->value('precio_uf'),
        ]);
    }

    public function show(Caja $caja): CajaResource
    {
        return new CajaResource($caja->load(['sucursal', 'contratacionVigente']));
    }

    public function store(StoreCajaRequest $request, NegocioActual $actual, CobroCajas $cobro): JsonResponse
    {
        $caja = DB::transaction(function () use ($request, $actual, $cobro): Caja {
            $negocio = Negocio::whereKey($actual->id())->lockForUpdate()->firstOrFail();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_cajas'), 403);
            $sucursal = Sucursal::where('public_id', $request->validated('sucursal'))->firstOrFail();
            $this->validarNombre($sucursal, $request->validated('nombre'));
            $cobro->exigirAceptacion($negocio, $sucursal, null, $request->boolean('acepto_cobro'), $request->input('cotizacion'));
            $caja = new Caja(['nombre' => $request->validated('nombre')]);
            $caja->forceFill(['negocio_id' => $negocio->id, 'sucursal_id' => $sucursal->id])->save();
            $caja->refresh();
            $cobro->contratar($negocio, $sucursal, $caja, $editor);

            return $caja->refresh();
        }, 3);

        return (new CajaResource($caja->load(['sucursal', 'contratacionVigente'])))->response()->setStatusCode(201);
    }

    public function update(UpdateCajaRequest $request, Caja $caja): CajaResource
    {
        DB::transaction(function () use ($request, $caja): void {
            Negocio::whereKey($caja->negocio_id)->lockForUpdate()->firstOrFail();
            $caja->refresh();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_cajas'), 403);
            $this->validarNombre($caja->sucursal, $request->validated('nombre'), $caja);
            $caja->update(['nombre' => $request->validated('nombre')]);
        }, 3);

        return new CajaResource($caja->load(['sucursal', 'contratacionVigente']));
    }

    private function validarNombre(Sucursal $sucursal, string $nombre, ?Caja $caja = null): void
    {
        if ($sucursal->cajas()->whereRaw('lower(nombre) = ?', [Str::lower($nombre)])
            ->when($caja, fn ($query) => $query->whereKeyNot($caja->id))->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una caja con este nombre en la sucursal.']);
        }
    }
}
