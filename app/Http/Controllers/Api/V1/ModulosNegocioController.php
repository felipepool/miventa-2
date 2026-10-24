<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Modulo;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateModulosNegocioRequest;
use App\Http\Resources\NegocioResource;
use App\Models\Negocio;
use App\Support\Contexto\NegocioActual;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModulosNegocioController extends Controller
{
    /**
     * Guarda los módulos activos, dentro de los que incluye el plan (el plan no
     * se cambia aquí). Desactivar un módulo solo lo oculta: sus datos se conservan.
     */
    public function __invoke(UpdateModulosNegocioRequest $request, NegocioActual $negocioActual): NegocioResource
    {
        return DB::transaction(function () use ($request, $negocioActual): NegocioResource {
            $negocio = Negocio::whereKey($negocioActual->id())->lockForUpdate()->firstOrFail();
            $modulos = array_map(Modulo::from(...), $request->validated('modulos'));
            foreach ($modulos as $modulo) {
                if (! $negocio->plan->incluye($modulo)) {
                    throw ValidationException::withMessages(['modulos' => 'El plan cambió. Recarga la página para revisar sus módulos.']);
                }
            }
            $negocio->update(['modulos' => $modulos]);
            $negocioActual->obtener()->refresh();

            return new NegocioResource($negocio);
        }, 3);
    }
}
