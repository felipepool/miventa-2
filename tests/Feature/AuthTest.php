<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the login page renders', function () {
    $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
});

test('public registration and password reset are disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->get('/password/reset')->assertNotFound();
});

test('a guest is redirected to login from the app', function () {
    $this->get('/')->assertRedirect('/app');
    $this->get('/app')->assertRedirect('/login');
    $this->get('/app/cualquier/ruta')->assertRedirect('/login');
});

test('a guest gets 401 json from the api', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

test('a user can log in and is sent to the app', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/app');

    $this->assertAuthenticatedAs($user);
});

test('a user cannot log in with a wrong password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an authenticated user sees the spa shell on any app path', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/app')->assertOk()->assertSee('id="app"', false);
    $this->actingAs($user)->get('/app/cualquier/ruta')->assertOk();
    $this->actingAs($user)->get('/login')->assertRedirect('/app');
});

test('the api returns the current user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]]);
});

test('the spa reaches the api with the session cookie from the blade login', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->getJson('/api/me', ['Referer' => config('app.url').'/app'])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

test('a user can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
