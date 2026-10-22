<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RolController extends Controller
{
    /**
     * Roles que se pueden asignar a un usuario, con su etiqueta.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn (Rol $rol) => ['value' => $rol->value, 'label' => $rol->etiqueta()], Rol::cases()),
        ]);
    }
}
