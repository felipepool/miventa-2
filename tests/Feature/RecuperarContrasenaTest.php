<?php

use App\Http\Responses\RespuestaEnlaceRestablecer;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['email' => 'duena@almacen.cl', 'username' => 'duena']);
});

test('a reset link to the spa is emailed in spanish with the login data', function () {
    Notification::fake();

    $this->postJson('/forgot-password', ['email' => 'duena@almacen.cl'])
        ->assertOk()
        ->assertExactJson(['message' => RespuestaEnlaceRestablecer::MENSAJE]);

    Notification::assertSentTo($this->admin, RestablecerContrasena::class, function (RestablecerContrasena $notificacion) {
        $correo = $notificacion->toMail($this->admin);
        $texto = implode(' ', $correo->introLines).' '.implode(' ', $correo->outroLines);

        return $correo->subject === 'Crea una contraseña nueva para MiVenta'
            && $correo->actionUrl === url("/restablecer-contrasena/{$notificacion->token}").'?email='.urlencode('duena@almacen.cl')
            && str_contains($texto, $this->admin->negocio->codigo)
            && str_contains($texto, 'duena');
    });
});

test('the answer is the same when the email is not registered or the account is disabled', function (Closure $preparar, string $correo) {
    Notification::fake();
    $preparar($this);

    $this->postJson('/forgot-password', ['email' => $correo])
        ->assertOk()
        ->assertExactJson(['message' => RespuestaEnlaceRestablecer::MENSAJE]);

    Notification::assertNothingSent();
})->with([
    'unknown email' => [fn () => null, 'nadie@almacen.cl'],
    'disabled account' => [fn ($test) => $test->admin->forceFill(['activo' => false])->save(), 'duena@almacen.cl'],
]);

test('the password is changed with a valid link and the user can log in with it', function () {
    $token = Password::broker()->createToken($this->admin);

    $this->postJson('/reset-password', [
        'token' => $token,
        'email' => 'duena@almacen.cl',
        'password' => 'clave-nueva-2026',
        'password_confirmation' => 'clave-nueva-2026',
    ])->assertOk()->assertExactJson(['message' => 'Tu contraseña fue restablecida.']);

    expect(Hash::check('clave-nueva-2026', $this->admin->fresh()->password))->toBeTrue();

    $this->postJson('/login', [
        'codigo_negocio' => $this->admin->negocio->codigo,
        'username' => 'duena',
        'password' => 'clave-nueva-2026',
    ])->assertOk();

    $this->assertAuthenticatedAs($this->admin);
});

test('an invalid or expired link returns 422 and does not change the password', function (Closure $token) {
    $this->postJson('/reset-password', [
        'token' => $token($this),
        'email' => 'duena@almacen.cl',
        'password' => 'clave-nueva-2026',
        'password_confirmation' => 'clave-nueva-2026',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'El enlace para restablecer la contraseña no es válido.']);

    expect(Hash::check('password', $this->admin->fresh()->password))->toBeTrue();
})->with([
    'invented' => [fn () => 'token-inventado'],
    'expired' => [function ($test) {
        $token = Password::broker()->createToken($test->admin);
        $test->travel(61)->minutes();

        return $token;
    }],
]);

test('reset link requests are rate limited by ip', function () {
    Notification::fake();

    foreach (range(1, 6) as $intento) {
        $this->postJson('/forgot-password', ['email' => "persona{$intento}@almacen.cl"])->assertOk();
    }

    $this->postJson('/forgot-password', ['email' => 'otra@almacen.cl'])->assertTooManyRequests();
});
