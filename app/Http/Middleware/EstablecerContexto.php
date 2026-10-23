<?php

namespace App\Http\Middleware;

use App\Models\Sucursal;
use App\Models\User;
use App\Support\Contexto\NegocioActual;
use App\Support\Contexto\SucursalActual;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establece el negocio y la sucursal activa del usuario autenticado para toda
 * la petición. Se ejecuta antes de resolver los modelos de la ruta, para que
 * el aislamiento por negocio también se aplique al route model binding.
 */
class EstablecerContexto
{
    public const string SUCURSALES_ACCESIBLES = 'miventa.sucursales_accesibles';

    public const string CUENTA_DESHABILITADA = 'Tu cuenta está deshabilitada. Contacta al administrador.';

    public function __construct(
        private NegocioActual $negocioActual,
        private SucursalActual $sucursalActual,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->puedeIniciarSesion()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $request->expectsJson()
                ? response()->json(['message' => self::CUENTA_DESHABILITADA], 401)
                : redirect('/login');
        }

        $negocio = $user->negocio;

        if ($negocio !== null) {
            $this->negocioActual->establecer($negocio);
            setPermissionsTeamId($negocio->id);

            $this->sucursalActual->establecer($this->resolverSucursal($request, $user));
        }

        return $next($request);
    }

    /**
     * Sucursal guardada en la sesión si el usuario aún tiene acceso a ella;
     * de lo contrario solo se elige automáticamente si queda una disponible.
     */
    private function resolverSucursal(Request $request, User $user): ?Sucursal
    {
        $sucursales = $user->sucursalesAccesibles();
        $request->attributes->set(self::SUCURSALES_ACCESIBLES, $sucursales);

        if (! $request->hasSession()) {
            return $sucursales->count() === 1 ? $sucursales->first() : null;
        }

        $sucursalId = $request->session()->get(SucursalActual::CLAVE_SESION);

        $sucursal = $sucursales->firstWhere('id', $sucursalId)
            ?? ($sucursales->count() === 1 ? $sucursales->first() : null);

        $request->session()->put(SucursalActual::CLAVE_SESION, $sucursal?->id);

        return $sucursal;
    }
}
