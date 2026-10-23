<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Planes\CobroSucursales;
use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Support\Contexto\NegocioActual;
use Illuminate\Http\JsonResponse;

class CotizacionSucursalController extends Controller
{
    public function __invoke(Sucursal $sucursal, NegocioActual $actual, CobroSucursales $cobro): JsonResponse
    {
        return response()->json(['data' => $cobro->resumen($actual->obtener(), $sucursal)]);
    }
}
