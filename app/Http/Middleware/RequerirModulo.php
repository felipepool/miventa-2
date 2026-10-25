<?php

namespace App\Http\Middleware;

use App\Enums\Modulo;
use App\Support\Contexto\NegocioActual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea (404) las rutas de un módulo que el negocio no tiene activo.
 * Uso: `modulo:modificadores`.
 */
class RequerirModulo
{
    public function __construct(private NegocioActual $negocioActual) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        abort_unless($this->negocioActual->obtener()?->tieneModulo(Modulo::from($modulo)), 404);

        return $next($request);
    }
}
