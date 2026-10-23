<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('changing state without a session returns 401 and preserves the account', function () {
    $usuario = User::factory()->cajero()->create();

    $this->patchJson("/api/v1/usuarios/{$usuario->public_id}/estado", ['activo' => false])->assertUnauthorized();

    expect($usuario->fresh()->activo)->toBeTrue();
});

test('operational roles receive 403 and cannot change account state', function (string $rol) {
    $editor = User::factory()->{$rol}()->create();
    $usuario = User::factory()->for($editor->negocio)->cajero()->create();

    $this->actingAs($editor)->patchJson("/api/v1/usuarios/{$usuario->public_id}/estado", ['activo' => false])->assertForbidden();

    expect($usuario->fresh()->activo)->toBeTrue();
})->with(['cajero', 'cocina', 'garzon']);

test('changing state in another business returns 404 and preserves the account', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->cajero()->create();

    $this->actingAs($admin)->patchJson("/api/v1/usuarios/{$usuario->public_id}/estado", ['activo' => false])->assertNotFound();

    expect($usuario->fresh()->activo)->toBeTrue();
});

test('an admin deactivates a user without deleting roles or branch assignments', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();
    $sucursales = $usuario->sucursales()->pluck('sucursales.id')->all();

    $this->actingAs($admin)->patchJson("/api/v1/usuarios/{$usuario->public_id}/estado", ['activo' => false])
        ->assertOk()->assertJsonPath('data.activo', false)->assertJsonPath('data.roles', ['cajero']);

    $this->assertModelExists($usuario);
    expect($usuario->fresh()->activo)->toBeFalse()
        ->and($usuario->sucursales()->pluck('sucursales.id')->all())->toBe($sucursales);
});

test('an admin reactivates an inactive account', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->inactivo()->create();

    $this->actingAs($admin)->patchJson("/api/v1/usuarios/{$usuario->public_id}/estado", ['activo' => true])
        ->assertOk()->assertJsonPath('data.activo', true);

    expect($usuario->fresh()->activo)->toBeTrue();
});

test('deactivating your own account returns 422 even when another admin exists', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->for($admin->negocio)->admin()->create(['username' => 'otro.admin']);

    $this->actingAs($admin)->patchJson("/api/v1/usuarios/{$admin->public_id}/estado", ['activo' => false])
        ->assertUnprocessable()->assertJsonValidationErrors(['activo' => 'No puedes desactivar tu propio usuario.']);

    expect($admin->fresh()->activo)->toBeTrue();
});

test('deactivating the last active admin returns 422 for a delegated user manager', function () {
    $admin = User::factory()->admin()->create();
    $editor = User::factory()->for($admin->negocio)->cajero()->create();
    setPermissionsTeamId($admin->negocio_id);
    $editor->givePermissionTo('gestionar_usuarios');

    $this->actingAs($editor)->patchJson("/api/v1/usuarios/{$admin->public_id}/estado", ['activo' => false])
        ->assertUnprocessable()->assertJsonValidationErrors(['activo' => 'El negocio debe tener al menos un administrador activo.']);

    expect($admin->fresh()->activo)->toBeTrue();
});

test('an admin can deactivate another administrator while retaining an active administrator', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->admin()->create(['username' => 'otro.admin']);

    $this->actingAs($admin)->patchJson("/api/v1/usuarios/{$usuario->public_id}/estado", ['activo' => false])
        ->assertOk()->assertJsonPath('data.activo', false);

    expect($admin->fresh()->activo)->toBeTrue()->and($usuario->fresh()->activo)->toBeFalse();
});

test('invalid state returns 422 without changing the account', function (array $payload) {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();

    $this->actingAs($admin)->patchJson("/api/v1/usuarios/{$usuario->public_id}/estado", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('activo');

    expect($usuario->fresh()->activo)->toBeTrue();
})->with(['missing' => [[]], 'invalid string' => [['activo' => 'desactivado']], 'null' => [['activo' => null]]]);

test('deactivated users lose access on their next api request', function () {
    $admin = User::factory()->admin()->create();
    $usuario = User::factory()->for($admin->negocio)->cajero()->create();

    $this->actingAs($admin)->patchJson("/api/v1/usuarios/{$usuario->public_id}/estado", ['activo' => false])->assertOk();

    $this->actingAs($usuario->fresh())->getJson('/api/v1/me')->assertUnauthorized();
    $this->assertGuest('web');
});
