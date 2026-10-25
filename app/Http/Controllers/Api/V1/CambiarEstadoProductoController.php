<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductoResource;
use App\Models\Negocio;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CambiarEstadoProductoController extends Controller
{
    /**
     * Activa o desactiva un producto. Los productos no se eliminan para conservar
     * el historial (ventas, inventario) asociado a ellos.
     */
    public function __invoke(Request $request, Producto $producto): ProductoResource
    {
        $activo = $request->validate(['activo' => ['required', 'boolean']])['activo'];

        DB::transaction(function () use ($request, $producto, $activo): void {
            Negocio::whereKey($producto->negocio_id)->lockForUpdate()->firstOrFail();
            $producto->refresh();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_catalogo'), 403);
            $producto->update(['activo' => $activo]);
        }, 3);

        return new ProductoResource($producto->load(['categoria', 'marca']));
    }
}
