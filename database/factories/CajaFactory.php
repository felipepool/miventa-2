<?php

namespace Database\Factories;

use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Caja> */
class CajaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sucursal_id' => Sucursal::factory(),
            'negocio_id' => fn (array $attributes) => Sucursal::withoutGlobalScopes()->findOrFail($attributes['sucursal_id'])->negocio_id,
            'nombre' => 'Caja '.fake()->unique()->bothify('##??'), 'activa' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['activa' => false]);
    }
}
