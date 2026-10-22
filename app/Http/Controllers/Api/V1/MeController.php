<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SesionResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Usuario autenticado y el contexto en el que trabaja.
     */
    public function __invoke(Request $request): SesionResource
    {
        return new SesionResource($request->user());
    }
}
