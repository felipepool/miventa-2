<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use App\Models\Negocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CambiarEstadoCategoriaController extends Controller
{
    /**
     * Activa o desactiva una categoría. Los productos que ya la usan la conservan.
     */
    public function __invoke(Request $request, Categoria $categoria): CategoriaResource
    {
        $activa = $request->validate(['activa' => ['required', 'boolean']])['activa'];

        DB::transaction(function () use ($request, $categoria, $activa): void {
            Negocio::whereKey($categoria->negocio_id)->lockForUpdate()->firstOrFail();
            $categoria->refresh();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_catalogo'), 403);
            $categoria->update(['activa' => $activa]);
        }, 3);

        return new CategoriaResource($categoria->loadCount('productos'));
    }
}
