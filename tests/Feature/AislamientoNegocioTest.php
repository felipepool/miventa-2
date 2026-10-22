<?php

use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Contexto\NegocioActual;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('queries only return records from the current business', function () {
    $negocioA = Negocio::factory()->conSucursalPrincipal()->create();
    $negocioB = Negocio::factory()->conSucursalPrincipal()->create();
    Sucursal::factory()->for($negocioB)->create();

    app(NegocioActual::class)->establecer($negocioA);

    expect(Sucursal::count())->toBe(1)
        ->and(Sucursal::pluck('negocio_id')->unique()->all())->toBe([$negocioA->id])
        ->and(Sucursal::find($negocioB->sucursales()->withoutGlobalScopes()->value('id')))->toBeNull();
});

test('new records are assigned to the current business even if another one is given', function () {
    $negocioA = Negocio::factory()->create();
    $negocioB = Negocio::factory()->create();

    app(NegocioActual::class)->establecer($negocioA);

    $sucursal = Sucursal::make(['nombre' => 'Sucursal Norte']);
    $sucursal->forceFill(['negocio_id' => $negocioB->id])->save();

    expect($sucursal->fresh()->negocio_id)->toBe($negocioA->id);
});

test('without a current business the scope is not applied', function () {
    Negocio::factory()->count(2)->conSucursalPrincipal()->create();

    expect(Sucursal::count())->toBe(2);
});

test('a user cannot select a branch from another business by its id', function () {
    $admin = User::factory()->admin()->create();
    $sucursalAjena = Negocio::factory()->conSucursalPrincipal()->create()
        ->sucursales()->withoutGlobalScopes()->first();

    $this->actingAs($admin)
        ->putJson("/api/v1/sucursal-actual/{$sucursalAjena->public_id}")
        ->assertNotFound();
});

test('the session only exposes the current business and its branches', function () {
    $admin = User::factory()->admin()->create();
    $negocio = $admin->negocio;
    $sucursalNorte = Sucursal::factory()->for($negocio)->create(['nombre' => 'Norte']);
    $otroNegocio = Negocio::factory()->conSucursalPrincipal()->create();
    Sucursal::factory()->for($otroNegocio)->create(['nombre' => 'Ajena']);

    $this->actingAs($admin)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.contexto.negocio', ['codigo' => $negocio->codigo, 'nombre' => $negocio->nombre])
        ->assertJsonPath('data.contexto.sucursal.nombre', 'Casa Matriz')
        ->assertJsonCount(2, 'data.contexto.sucursales')
        ->assertJsonPath('data.contexto.sucursales.1.id', $sucursalNorte->public_id)
        ->assertJsonMissing(['nombre' => 'Ajena']);
});
