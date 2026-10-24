<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNegocioRequest;
use App\Http\Resources\NegocioResource;
use App\Support\Contexto\NegocioActual;

class NegocioController extends Controller
{
    public function __construct(private NegocioActual $negocioActual) {}

    public function show(): NegocioResource
    {
        return new NegocioResource($this->negocioActual->obtener());
    }

    /**
     * Datos del negocio. El código no se modifica: los usuarios lo usan para ingresar.
     */
    public function update(UpdateNegocioRequest $request): NegocioResource
    {
        $negocio = $this->negocioActual->obtener();
        $negocio->update($request->validated());

        return new NegocioResource($negocio);
    }
}
