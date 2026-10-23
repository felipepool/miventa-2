<?php

use App\Models\Sucursal;
use App\Models\User;
use App\Support\Contexto\SucursalActual;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('logging in with several accessible branches requires an explicit selection', function () {
    $admin = User::factory()->admin()->create();
    Sucursal::factory()->for($admin->negocio)->create(['nombre' => 'Alameda']);

    $this->postJson('/login', [
        'codigo_negocio' => $admin->negocio->codigo,
        'username' => $admin->username,
        'password' => 'password',
    ])->assertOk();

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.contexto.sucursal', null)
        ->assertJsonCount(2, 'data.contexto.sucursales');
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

test('login automatically selects the only accessible branch even when the business has others', function () {
    $cajero = User::factory()->cajero()->create();
    Sucursal::factory()->for($cajero->negocio)->create();
    $this->postJson('/login', ['codigo_negocio' => $cajero->negocio->codigo, 'username' => $cajero->username, 'password' => 'password'])->assertOk();
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.contexto.sucursal.nombre', 'Casa Matriz')
        ->assertJsonCount(1, 'data.contexto.sucursales');
});

test('only active assigned branches and their addresses are offered to an operational user', function () {
    $cajero = User::factory()->cajero()->create();
    $norte = Sucursal::factory()->for($cajero->negocio)->create(['nombre' => 'Norte', 'direccion' => 'Av. Norte 123']);
    $inactiva = Sucursal::factory()->for($cajero->negocio)->inactiva()->create();
    $ajena = Sucursal::factory()->create();
    $noAsignada = Sucursal::factory()->for($cajero->negocio)->create();
    $cajero->sucursales()->attach([$norte->id, $inactiva->id, $ajena->id]);
    $this->actingAs($cajero)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.contexto.sucursal', null)->assertJsonCount(2, 'data.contexto.sucursales')
        ->assertJsonPath('data.contexto.sucursales.1.direccion', 'Av. Norte 123')
        ->assertJsonMissing(['id' => $inactiva->public_id])->assertJsonMissing(['id' => $ajena->public_id])
        ->assertJsonMissing(['id' => $noAsignada->public_id]);
});

test('a new login does not reuse a previously confirmed branch', function () {
    $admin = User::factory()->admin()->create();
    $norte = Sucursal::factory()->for($admin->negocio)->create();
    $this->withSession([SucursalActual::CLAVE_SESION => $norte->id])->postJson('/login', [
        'codigo_negocio' => $admin->negocio->codigo, 'username' => $admin->username, 'password' => 'password',
    ])->assertOk()->assertSessionMissing(SucursalActual::CLAVE_SESION);
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.sucursal', null);
    $this->putJson('/api/v1/sucursal-actual/'.$norte->public_id)->assertOk();
    $this->postJson('/logout')->assertNoContent();
    $this->postJson('/login', ['codigo_negocio' => $admin->negocio->codigo, 'username' => $admin->username, 'password' => 'password'])->assertOk();
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.sucursal', null);
});

test('protected reads and writes return 409 until a branch is selected', function () {
    $admin = User::factory()->admin()->create();
    $norte = Sucursal::factory()->for($admin->negocio)->create();
    $this->actingAs($admin)->getJson('/api/v1/usuarios')->assertStatus(409)->assertJsonPath('code', 'sucursal_requerida');
    $this->getJson('/api/v1/sucursales')->assertStatus(409);
    $this->postJson('/api/v1/sucursales', ['nombre' => 'No creada'])->assertStatus(409);
    $this->assertDatabaseMissing('sucursales', ['nombre' => 'No creada']);
    $this->putJson('/api/v1/sucursal-actual/'.$norte->public_id)->assertOk();
    $this->getJson('/api/v1/usuarios')->assertOk();
    $this->getJson('/api/v1/sucursales')->assertOk();
});

test('a user without available branches can read the session and log out but cannot use protected endpoints', function () {
    $cajero = User::factory()->cajero()->create();
    $cajero->sucursales()->detach();
    $this->actingAs($cajero)->getJson('/api/v1/me')->assertOk()->assertJsonCount(0, 'data.contexto.sucursales');
    $this->getJson('/api/v1/roles')->assertStatus(409);
    $this->postJson('/logout')->assertNoContent();
    $this->assertGuest();
});

test('a branch from another business cannot be selected', function () {
    $admin = User::factory()->admin()->create();
    $ajena = Sucursal::factory()->create();
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$ajena->public_id)->assertNotFound();
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.sucursal.nombre', 'Casa Matriz');
});

test('removing access to the current branch requires a new choice when several remain', function () {
    $cajero = User::factory()->cajero()->create();
    $norte = Sucursal::factory()->for($cajero->negocio)->create();
    $sur = Sucursal::factory()->for($cajero->negocio)->create();
    $cajero->sucursales()->attach([$norte->id, $sur->id]);
    $this->actingAs($cajero)->putJson('/api/v1/sucursal-actual/'.$norte->public_id)->assertOk();
    $cajero->sucursales()->detach($norte);
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.sucursal', null)->assertJsonCount(2, 'data.contexto.sucursales');
    $this->putJson('/api/v1/sucursal-actual/'.$norte->public_id)->assertForbidden();
});

test('deactivating the selected branch blocks operations when several other branches remain', function () {
    $admin = User::factory()->admin()->create();
    $norte = Sucursal::factory()->for($admin->negocio)->create();
    Sucursal::factory()->for($admin->negocio)->create();
    $this->actingAs($admin)->putJson('/api/v1/sucursal-actual/'.$norte->public_id)->assertOk();
    $norte->update(['activa' => false]);
    $this->getJson('/api/v1/usuarios')->assertStatus(409)->assertJsonPath('code', 'sucursal_requerida');
    $this->getJson('/api/v1/me')->assertJsonPath('data.contexto.sucursal', null);
    $this->putJson('/api/v1/sucursal-actual/'.$norte->public_id)->assertForbidden();
});

test('guests cannot select a branch', function () {
    $sucursal = Sucursal::factory()->create();
    $this->putJson('/api/v1/sucursal-actual/'.$sucursal->public_id)->assertUnauthorized();
});
