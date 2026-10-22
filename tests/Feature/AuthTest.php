<?php

use App\Models\Negocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

uses(RefreshDatabase::class);

/**
 * @return array{codigo_negocio: string, username: string, password: string}
 */
function credencialesDe(User $user, string $password = 'password'): array
{
    return [
        'codigo_negocio' => $user->negocio->codigo,
        'username' => $user->username,
        'password' => $password,
    ];
}

test('the spa shell is served on public and private paths', function (string $ruta) {
    $this->get($ruta)->assertOk()->assertSee('<div id="app"></div>', false);
})->with(['/login', '/olvide-contrasena', '/dashboard', '/cualquier/ruta']);

test('the root redirects to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

test('an unknown api path returns 404 json instead of the spa shell', function () {
    $this->getJson('/api/v1/no-existe')->assertNotFound()->assertJsonStructure(['message']);
});

test('public registration is disabled', function () {
    $this->postJson('/register', [
        'name' => 'Nueva',
        'username' => 'nueva',
        'email' => 'nueva@almacen.cl',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertMethodNotAllowed(); // Solo existe el GET del cascarón de la SPA.

    $this->assertDatabaseMissing('users', ['email' => 'nueva@almacen.cl']);
});

test('a guest gets 401 from the api', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('users log in with business code, username and password and the spa reaches the api', function () {
    $user = User::factory()->admin()->create();

    $this->postJson('/login', credencialesDe($user))
        ->assertOk()
        ->assertExactJson(['two_factor' => false]);

    $this->assertAuthenticatedAs($user);

    $this->getJson('/api/v1/me', ['Referer' => config('app.url').'/dashboard'])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->public_id);
});

test('business code and username are not case sensitive', function () {
    $user = User::factory()->create(['username' => 'caja.1']);

    $this->postJson('/login', [
        'codigo_negocio' => '  '.strtoupper($user->negocio->codigo),
        'username' => 'CAJA.1',
        'password' => 'password',
    ])->assertOk();

    $this->assertAuthenticatedAs($user);
});

test('two businesses can have the same username and each one logs into its own', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create(['password' => 'otra-clave']);

    expect($adminA->username)->toBe('admin')->and($adminB->username)->toBe('admin');

    $this->postJson('/login', credencialesDe($adminB, 'otra-clave'))->assertOk();

    $this->assertAuthenticatedAs($adminB);
});

test('invalid credentials return 422 with the same generic message', function (Closure $credenciales) {
    $user = User::factory()->create();
    User::factory()->create(['username' => 'ajeno']);

    $this->postJson('/login', $credenciales($user))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username' => 'Las credenciales no son correctas.']);

    $this->assertGuest();
})->with([
    'wrong password' => [fn (User $user) => credencialesDe($user, 'clave-incorrecta')],
    'unknown business code' => [fn (User $user) => [...credencialesDe($user), 'codigo_negocio' => 'no-existe']],
    'user from another business' => [fn (User $user) => [...credencialesDe($user), 'username' => 'ajeno']],
    'unknown username' => [fn (User $user) => [...credencialesDe($user), 'username' => 'nadie']],
]);

test('the business code is required', function () {
    $user = User::factory()->create();

    $this->postJson('/login', [...credencialesDe($user), 'codigo_negocio' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['codigo_negocio' => 'El campo código de negocio es obligatorio.']);

    $this->assertGuest();
});

test('login attempts are rate limited', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $intento) {
        $this->postJson('/login', credencialesDe($user, 'clave-incorrecta'))->assertUnprocessable();
    }

    $this->postJson('/login', credencialesDe($user))->assertTooManyRequests();

    $this->assertGuest();
});

test('inactive users cannot log in', function () {
    $user = User::factory()->inactivo()->create();

    $this->postJson('/login', credencialesDe($user))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username' => 'Tu cuenta está deshabilitada. Contacta al administrador.']);

    $this->assertGuest();
});

test('users of an inactive business cannot log in', function () {
    $user = User::factory()->for(Negocio::factory()->inactivo())->create();

    $this->postJson('/login', credencialesDe($user))->assertJsonValidationErrors('username');

    $this->assertGuest();
});

test('a user deactivated during their session gets 401 on the next api request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/me')->assertOk();

    $user->update(['activo' => false]);

    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Tu cuenta está deshabilitada. Contacta al administrador.']);

    // La sesión de la SPA quedó cerrada.
    $this->assertGuest('web');
});

test('the spa is stateful on a host other than the app url', function () {
    $request = Request::create('http://otro-host.test/api/v1/me', server: ['HTTP_REFERER' => 'http://otro-host.test/']);

    expect(EnsureFrontendRequestsAreStateful::fromFrontend($request))->toBeTrue();
});

test('users log out with json', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/logout')
        ->assertNoContent();

    $this->assertGuest();
});
