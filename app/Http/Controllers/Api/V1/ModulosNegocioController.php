<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Modulo;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateModulosNegocioRequest;
use App\Http\Resources\NegocioResource;
use App\Support\Contexto\NegocioActual;

class ModulosNegocioController extends Controller
{
    /**
     * Guarda los módulos activos, dentro de los que incluye el plan (el plan no
     * se cambia aquí). Desactivar un módulo solo lo oculta: sus datos se conservan.
     */
    public function __invoke(UpdateModulosNegocioRequest $request, NegocioActual $negocioActual): NegocioResource
    {
        $negocio = $negocioActual->obtener();
        $negocio->update([
            'modulos' => array_map(Modulo::from(...), $request->validated('modulos')),
        ]);

        return new NegocioResource($negocio);
    }
}
