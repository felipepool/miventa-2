<?php

use App\Http\Responses\RespuestaEnlaceRestablecer;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'duena@almacen.cl']);
});

test('a reset link pointing to the spa is emailed', function () {
    Notification::fake();

    $this->postJson('/forgot-password', ['email' => 'duena@almacen.cl'])
        ->assertOk()
        ->assertExactJson(['message' => RespuestaEnlaceRestablecer::MENSAJE]);

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notificacion) {
        $enlace = $notificacion->toMail($this->user)->actionUrl;

        return $enlace === url("/restablecer-contrasena/{$notificacion->token}").'?email='.urlencode('duena@almacen.cl');
    });
});

test('the answer is the same when the email is not registered', function () {
    Notification::fake();

    $this->postJson('/forgot-password', ['email' => 'nadie@almacen.cl'])
        ->assertOk()
        ->assertExactJson(['message' => RespuestaEnlaceRestablecer::MENSAJE]);

    Notification::assertNothingSent();
});

test('the password is changed with a valid link and the user can log in with it', function () {
    $token = Password::broker()->createToken($this->user);

    $this->postJson('/reset-password', [
        'token' => $token,
        'email' => 'duena@almacen.cl',
        'password' => 'clave-nueva-2026',
        'password_confirmation' => 'clave-nueva-2026',
    ])->assertOk()->assertExactJson(['message' => 'Tu contraseña fue restablecida.']);

    expect(Hash::check('clave-nueva-2026', $this->user->fresh()->password))->toBeTrue();

    $this->postJson('/login', ['email' => 'duena@almacen.cl', 'password' => 'clave-nueva-2026'])->assertOk();
    $this->assertAuthenticatedAs($this->user);
});

test('an invalid or expired link returns 422 and does not change the password', function (Closure $token) {
    $this->postJson('/reset-password', [
        'token' => $token($this),
        'email' => 'duena@almacen.cl',
        'password' => 'clave-nueva-2026',
        'password_confirmation' => 'clave-nueva-2026',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'El enlace para restablecer la contraseña no es válido.']);

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
})->with([
    'invented' => [fn () => 'token-inventado'],
    'expired' => [function ($test) {
        $token = Password::broker()->createToken($test->user);
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
