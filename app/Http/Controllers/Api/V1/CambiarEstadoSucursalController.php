<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SucursalResource;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CambiarEstadoSucursalController extends Controller
{
    /**
     * Activa o desactiva una sucursal. Las sucursales no se eliminan para
     * conservar su historial (stock, cajas, ventas).
     */
    public function __invoke(Request $request, Sucursal $sucursal): SucursalResource
    {
        $activa = $request->validate(['activa' => ['required', 'boolean']])['activa'];

        if (! $activa && $sucursal->es_principal) {
            throw ValidationException::withMessages(['activa' => 'La sucursal principal no se puede desactivar.']);
        }

        $sucursal->update(['activa' => $activa]);

        return new SucursalResource($sucursal->loadCount('usuarios'));
    }
}
