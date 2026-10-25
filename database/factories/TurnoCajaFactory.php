<?php

namespace Database\Factories;

use App\Models\Caja;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TurnoCaja> */
class TurnoCajaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'caja_id' => Caja::factory(),
            'negocio_id' => fn (array $attributes) => Caja::withoutGlobalScopes()->findOrFail($attributes['caja_id'])->negocio_id,
            'sucursal_id' => fn (array $attributes) => Caja::withoutGlobalScopes()->findOrFail($attributes['caja_id'])->sucursal_id,
            'user_id' => fn (array $attributes) => User::factory()->state(['negocio_id' => $attributes['negocio_id']]),
            'numero' => fn (array $attributes) => (int) TurnoCaja::withoutGlobalScopes()->where('caja_id', $attributes['caja_id'])->max('numero') + 1,
            'monto_inicial' => 10000,
            'nota_apertura' => null,
            'abierta_at' => now()->subHours(2),
        ];
    }

    /** Turno ya cerrado, con lo contado igual a lo esperado. */
    public function cerrado(): static
    {
        return $this->state(fn (array $attributes) => [
            'cerrada_at' => now()->subHour(), 'cerrada_por_id' => $attributes['user_id'],
            'monto_esperado' => $attributes['monto_inicial'], 'monto_contado' => $attributes['monto_inicial'], 'diferencia' => 0,
        ]);
    }
}
