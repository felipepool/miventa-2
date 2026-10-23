<?php

namespace App\Http\Middleware;

use App\Support\Contexto\SucursalActual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequerirSucursalActual
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(SucursalActual::class)->existe()) {
            return response()->json([
                'message' => 'Selecciona una sucursal disponible para continuar.',
                'code' => 'sucursal_requerida',
            ], 409);
        }

        return $next($request);
    }
}
