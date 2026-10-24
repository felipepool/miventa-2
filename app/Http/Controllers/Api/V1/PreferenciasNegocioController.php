<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreferenciasNegocioRequest;
use App\Http\Resources\NegocioResource;
use App\Support\Contexto\NegocioActual;

class PreferenciasNegocioController extends Controller
{
    /**
     * Preferencias de operación: el cierre ciego de caja y hasta qué porcentaje
     * pueden descontar los cajeros.
     */
    public function __invoke(UpdatePreferenciasNegocioRequest $request, NegocioActual $negocioActual): NegocioResource
    {
        $negocio = $negocioActual->obtener();
        $negocio->update($request->validated());

        return new NegocioResource($negocio);
    }
}
