<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSucursalRequest;
use App\Http\Requests\UpdateSucursalRequest;
use App\Http\Resources\SucursalResource;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SucursalController extends Controller
{
    /**
     * Sucursales del negocio, la principal primero.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['activa', 'inactiva'])],
        ]);

        $sucursales = Sucursal::query()
            ->withCount('usuarios')
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $buscar) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('nombre', "%{$buscar}%")
                    ->orWhereLike('direccion', "%{$buscar}%"),
            ))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('activa', $estado === 'activa'))
            ->orderByDesc('es_principal')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return SucursalResource::collection($sucursales);
    }

    public function store(StoreSucursalRequest $request): JsonResponse
    {
        $sucursal = Sucursal::create($request->validated());

        return (new SucursalResource($sucursal->loadCount('usuarios')))->response()->setStatusCode(201);
    }

    public function update(UpdateSucursalRequest $request, Sucursal $sucursal): SucursalResource
    {
        $sucursal->update($request->validated());

        return new SucursalResource($sucursal->loadCount('usuarios'));
    }
}
