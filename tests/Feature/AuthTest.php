<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        'email' => 'nueva@almacen.cl',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertMethodNotAllowed(); // Solo existe el GET del cascarón de la SPA.

    $this->assertDatabaseMissing('users', ['email' => 'nueva@almacen.cl']);
});

test('a guest gets 401 from the api', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('the api returns the current user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]]);
});

test('a user logs in with json and the spa reaches the api with the session cookie', function () {
    $user = User::factory()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertExactJson(['two_factor' => false]);

    $this->assertAuthenticatedAs($user);

    $this->getJson('/api/v1/me', ['Referer' => config('app.url').'/dashboard'])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

test('a wrong password returns 422 in spanish and does not log in', function () {
    $user = User::factory()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'incorrecta'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'Las credenciales no son correctas.']);

    $this->assertGuest();
});

test('login is rate limited after five failed attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $intento) {
        $this->postJson('/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertUnprocessable();
    }

    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();

    $this->assertGuest();
});

test('a user logs out with json', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/logout')
        ->assertNoContent();

    $this->assertGuest();
});
