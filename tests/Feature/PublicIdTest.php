<?php

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->sucursal = Sucursal::factory()->for($this->admin->negocio)->create();
});

test('records get a unique public id when created', function () {
    $cajero = User::factory()->cajero()->for($this->admin->negocio)->create();

    expect(Str::isUlid($cajero->public_id))->toBeTrue()
        ->and(Str::isUlid($this->sucursal->public_id))->toBeTrue()
        ->and($cajero->public_id)->not->toBe($this->admin->public_id);
});

test('urls do not accept internal numeric ids', function () {
    $this->actingAs($this->admin)
        ->putJson("/api/v1/sucursal-actual/{$this->sucursal->id}")
        ->assertNotFound();
});

test('the session only exposes public ids', function () {
    $respuesta = $this->actingAs($this->admin)->getJson('/api/v1/me')->assertOk();

    $ids = [
        $respuesta->json('data.user.id'),
        $respuesta->json('data.contexto.sucursal.id'),
        ...$respuesta->json('data.contexto.sucursales.*.id'),
    ];

    foreach ($ids as $id) {
        expect(Str::isUlid((string) $id))->toBeTrue("Se expone un ID interno: {$id}");
    }

    expect($respuesta->json('data.contexto.negocio'))->not->toHaveKey('id');
});
