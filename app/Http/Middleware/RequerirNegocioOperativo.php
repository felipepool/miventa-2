<?php

namespace App\Http\Middleware;

use App\Support\Contexto\NegocioActual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequerirNegocioOperativo
{
    public function __construct(private NegocioActual $negocioActual) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && ! $request->routeIs('api.v1.plan.update', 'api.v1.sucursal-actual')
            && $this->negocioActual->obtener()?->soloLectura()) {
            return response()->json(['code' => 'negocio_solo_lectura',
                'message' => 'La prueba terminó. El negocio está en solo lectura. Contrata un plan para volver a operar.'], 403);
        }

        return $next($request);
    }
}
