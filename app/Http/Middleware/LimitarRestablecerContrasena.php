<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify no limita por IP las solicitudes para recuperar la contraseña (solo
 * una cada 60 s por correo): se aplica el limitador "restablecer" a esas rutas.
 */
class LimitarRestablecerContrasena
{
    public function __construct(private ThrottleRequests $limitador) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->routeIs('password.email', 'password.update')) {
            return $this->limitador->handle($request, $next, 'restablecer');
        }

        return $next($request);
    }
}
