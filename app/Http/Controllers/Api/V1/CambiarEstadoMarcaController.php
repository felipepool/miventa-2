<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MarcaResource;
use App\Models\Marca;
use App\Models\Negocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CambiarEstadoMarcaController extends Controller
{
    /**
     * Activa o desactiva una marca. Los productos que ya la usan la conservan.
     */
    public function __invoke(Request $request, Marca $marca): MarcaResource
    {
        $activa = $request->validate(['activa' => ['required', 'boolean']])['activa'];

        DB::transaction(function () use ($request, $marca, $activa): void {
            Negocio::whereKey($marca->negocio_id)->lockForUpdate()->firstOrFail();
            $marca->refresh();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_catalogo'), 403);
            $marca->update(['activa' => $activa]);
        }, 3);

        return new MarcaResource($marca->loadCount('productos'));
    }
}
