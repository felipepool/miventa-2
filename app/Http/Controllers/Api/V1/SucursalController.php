<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Planes\CobroSucursales;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSucursalRequest;
use App\Http\Requests\UpdateSucursalRequest;
use App\Http\Resources\SucursalResource;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Support\Contexto\NegocioActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SucursalController extends Controller
{
    public function index(Request $request, NegocioActual $actual, CobroSucursales $cobro): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['activa', 'inactiva'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $sucursales = Sucursal::query()->withCount(['usuarios', 'cajas'])
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $buscar) => $query->where(
                fn (Builder $query) => $query->whereLike('nombre', "%{$buscar}%")->orWhereLike('direccion', "%{$buscar}%"),
            ))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('activa', $estado === 'activa'))
            ->orderByDesc('es_principal')->orderBy('nombre')->orderBy('id')->paginate(15)->withQueryString();

        return SucursalResource::collection($sucursales)->additional(['cobro_sucursal' => $cobro->resumen($actual->obtener())]);
    }

    public function show(Sucursal $sucursal): SucursalResource
    {
        return new SucursalResource($sucursal->loadCount(['usuarios', 'cajas']));
    }

    public function store(StoreSucursalRequest $request, NegocioActual $actual, CobroSucursales $cobro): JsonResponse
    {
        $sucursal = DB::transaction(function () use ($request, $actual, $cobro): Sucursal {
            $negocio = Negocio::whereKey($actual->id())->lockForUpdate()->firstOrFail();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_sucursales'), 403);
            $this->validarNombre($request->validated('nombre'));
            $cobro->exigirAceptacion($negocio, null, $request->boolean('acepto_cobro'), $request->input('cotizacion'));
            $sucursal = new Sucursal($request->safe()->only(['nombre', 'direccion', 'telefono']));
            $sucursal->es_principal = ! $negocio->sucursales()->withoutGlobalScopes()->exists();
            $sucursal->save();
            $sucursal->refresh();
            $cobro->contratar($negocio, $sucursal, $editor);

            return $sucursal;
        }, 3);

        return (new SucursalResource($sucursal->loadCount(['usuarios', 'cajas'])))->response()->setStatusCode(201);
    }

    public function update(UpdateSucursalRequest $request, Sucursal $sucursal): SucursalResource
    {
        DB::transaction(function () use ($request, $sucursal): void {
            Negocio::whereKey($sucursal->negocio_id)->lockForUpdate()->firstOrFail();
            $sucursal->refresh();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_sucursales'), 403);
            $this->validarNombre($request->validated('nombre'), $sucursal);
            $sucursal->update($request->safe()->only(['nombre', 'direccion', 'telefono']));
        }, 3);

        return new SucursalResource($sucursal->loadCount(['usuarios', 'cajas']));
    }

    private function validarNombre(string $nombre, ?Sucursal $sucursal = null): void
    {
        if (Sucursal::where('nombre', $nombre)->when($sucursal, fn ($query) => $query->whereKeyNot($sucursal->id))->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una sucursal con este nombre en tu negocio.']);
        }
    }
}
