<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GrupoModificadorResource;
use App\Models\GrupoModificador;
use App\Models\Negocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CambiarEstadoGrupoModificadorController extends Controller
{
    /**
     * Activa o desactiva un grupo. Los productos que ya lo usan lo conservan.
     */
    public function __invoke(Request $request, GrupoModificador $grupoModificador): GrupoModificadorResource
    {
        $activo = $request->validate(['activo' => ['required', 'boolean']])['activo'];

        DB::transaction(function () use ($request, $grupoModificador, $activo): void {
            Negocio::whereKey($grupoModificador->negocio_id)->lockForUpdate()->firstOrFail();
            $grupoModificador->refresh();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_catalogo'), 403);
            $grupoModificador->update(['activo' => $activo]);
        }, 3);

        return new GrupoModificadorResource($grupoModificador->load('modificadores')->loadCount('productos'));
    }
}
