<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Middleware\EstablecerContexto;
use App\Http\Responses\RespuestaEnlaceRestablecer;
use App\Models\Negocio;
use App\Models\User;
use App\Support\Contexto\SucursalActual;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, RespuestaEnlaceRestablecer::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, RespuestaEnlaceRestablecer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && request()->hasSession()) {
                request()->session()->forget(SucursalActual::CLAVE_SESION);
            }
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Recuperar contraseña: 6 solicitudes por minuto por IP (ver LimitarRestablecerContrasena).
        RateLimiter::for('restablecer', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));

        // Ingreso con código de negocio + usuario: el usuario solo se busca dentro de su negocio.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $request->validate([
                'codigo_negocio' => ['required', 'string', 'max:30'],
            ]);

            $negocio = Negocio::where('codigo', Str::lower(trim($request->string('codigo_negocio'))))->first();

            $user = $negocio?->usuarios()
                ->where('username', Str::lower(trim($request->string(Fortify::username()))))
                ->first();

            if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
                return null;
            }

            if (! $user->puedeIniciarSesion()) {
                throw ValidationException::withMessages([
                    Fortify::username() => EstablecerContexto::CUENTA_DESHABILITADA,
                ]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower(
                $request->input('codigo_negocio').'|'.$request->input(Fortify::username()),
            ).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
