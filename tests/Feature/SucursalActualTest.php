<?php

use App\Models\Sucursal;
use App\Models\User;
use App\Support\Contexto\SucursalActual;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the main branch is selected after logging in', function () {
    $admin = User::factory()->admin()->create();
    Sucursal::factory()->for($admin->negocio)->create(['nombre' => 'Alameda']);

    $this->postJson('/login', [
        'codigo_negocio' => $admin->negocio->codigo,
        'username' => $admin->username,
        'password' => 'password',
    ])->assertOk();

    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.sucursal.nombre', 'Casa Matriz');
});

test('an admin can switch to any active branch of the business', function () {
    $admin = User::factory()->admin()->create();
    $sucursalNorte = Sucursal::factory()->for($admin->negocio)->create(['nombre' => 'Norte']);

    $this->actingAs($admin)
        ->putJson("/api/v1/sucursal-actual/{$sucursalNorte->public_id}")
        ->assertOk()
        ->assertJsonPath('data.contexto.sucursal.id', $sucursalNorte->public_id)
        ->assertSessionHas(SucursalActual::CLAVE_SESION, $sucursalNorte->id);

    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.sucursal.id', $sucursalNorte->public_id);
});

test('a cashier gets 403 when switching to a branch not assigned to them', function () {
    $cajero = User::factory()->cajero()->create();
    $sucursalNoAsignada = Sucursal::factory()->for($cajero->negocio)->create();

    $this->actingAs($cajero)
        ->putJson("/api/v1/sucursal-actual/{$sucursalNoAsignada->public_id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes acceso a esta sucursal.');

    $this->getJson('/api/v1/me')
        ->assertJsonPath('data.contexto.sucursal.nombre', 'Casa Matriz')
        ->assertJsonCount(1, 'data.contexto.sucursales');
});

test('inactive branches return 403', function () {
    $admin = User::factory()->admin()->create();
    $sucursalInactiva = Sucursal::factory()->for($admin->negocio)->inactiva()->create();

    $this->actingAs($admin)
        ->putJson("/api/v1/sucursal-actual/{$sucursalInactiva->public_id}")
        ->assertForbidden();
});

test('a branch stored in the session that is no longer accessible falls back to the main branch', function () {
    $cajero = User::factory()->cajero()->create();
    $sucursalNoAsignada = Sucursal::factory()->for($cajero->negocio)->create();

    $this->actingAs($cajero)
        ->withSession([SucursalActual::CLAVE_SESION => $sucursalNoAsignada->id])
        ->getJson('/api/v1/me')
        ->assertJsonPath('data.contexto.sucursal.nombre', 'Casa Matriz');
});

test('a cashier without assigned branches has no current branch', function () {
    $cajero = User::factory()->cajero()->create();
    $cajero->sucursales()->detach();

    $this->actingAs($cajero)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.contexto.sucursal', null)
        ->assertJsonCount(0, 'data.contexto.sucursales');
});
