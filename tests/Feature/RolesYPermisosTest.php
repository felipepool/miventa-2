<?php

use App\Enums\Permiso;
use App\Enums\Rol;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin receives every permission', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/api/v1/me')
        ->assertJsonPath('data.roles', ['admin'])
        ->assertJsonPath('data.permisos', array_map(fn (Permiso $permiso) => $permiso->value, Permiso::cases()));
});

test('each role receives only its permissions', function (string $estado, array $esperados) {
    $usuario = User::factory()->{$estado}()->create();

    $this->actingAs($usuario)
        ->getJson('/api/v1/me')
        ->assertJsonPath('data.roles', [$estado])
        ->assertJsonPath('data.permisos', $esperados);
})->with([
    'cashier sells, operates the register, sees the kitchen and serves tables' => ['cajero', ['operar_caja', 'vender', 'ver_cocina', 'atender_mesas']],
    'kitchen only sees the kitchen' => ['cocina', ['ver_cocina']],
    'waiter only serves tables' => ['garzon', ['atender_mesas']],
]);

test('a user with several roles receives the union of their permissions', function () {
    $usuario = User::factory()->conRoles(Rol::Cocina, Rol::Garzon)->create();

    $this->actingAs($usuario)
        ->getJson('/api/v1/me')
        ->assertJsonPath('data.roles', ['cocina', 'garzon'])
        ->assertJsonPath('data.permisos', ['ver_cocina', 'atender_mesas']);
});

test('roles are scoped to the business they were assigned in', function () {
    $admin = User::factory()->admin()->create();
    $otroNegocio = Negocio::factory()->create();

    setPermissionsTeamId($admin->negocio_id);
    expect($admin->fresh()->hasRole(Rol::Admin))->toBeTrue();

    setPermissionsTeamId($otroNegocio->id);
    expect($admin->fresh()->hasRole(Rol::Admin))->toBeFalse();
});
