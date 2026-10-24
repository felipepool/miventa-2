<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Planes\CobroCajas;
use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Sucursal;
use App\Support\Contexto\NegocioActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CotizacionCajaController extends Controller
{
    public function __invoke(Request $request, NegocioActual $actual, CobroCajas $cobro): JsonResponse
    {
        $datos = $request->validate(['sucursal' => ['required', 'string', 'ulid'], 'caja' => ['nullable', 'string', 'ulid']]);
        $sucursal = Sucursal::where('public_id', $datos['sucursal'])->firstOrFail();
        $caja = ! empty($datos['caja']) ? Caja::where('sucursal_id', $sucursal->id)->where('public_id', $datos['caja'])->firstOrFail() : null;

        return response()->json(['data' => $cobro->resumen($actual->obtener(), $sucursal, $caja)]);
    }
}
