<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SesionResource;
use App\Models\Sucursal;
use App\Support\Contexto\SucursalActual;
use Illuminate\Http\Request;

class SucursalActualController extends Controller
{
    /**
     * Cambia la sucursal activa del usuario en la sesión actual y devuelve la
     * sesión actualizada. El route model binding ya filtra por negocio: una
     * sucursal de otro negocio da 404.
     */
    public function __invoke(Request $request, Sucursal $sucursal, SucursalActual $sucursalActual): SesionResource
    {
        abort_unless(
            $request->user()->sucursalesAccesibles()->contains($sucursal),
            403,
            'No tienes acceso a esta sucursal.',
        );

        // Con un token (sin sesión) el cambio aplica solo a esta petición.
        if ($request->hasSession()) {
            $request->session()->put(SucursalActual::CLAVE_SESION, $sucursal->id);
        }

        $sucursalActual->establecer($sucursal);

        return new SesionResource($request->user());
    }
}
