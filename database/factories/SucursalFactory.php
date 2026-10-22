<?php

namespace Database\Factories;

use App\Models\Negocio;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sucursal>
 */
class SucursalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'nombre' => 'Sucursal '.fake()->unique()->city(),
            'direccion' => fake()->streetAddress(),
            'telefono' => fake()->phoneNumber(),
            'es_principal' => false,
            'activa' => true,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Casa Matriz',
            'es_principal' => true,
        ]);
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => [
            'activa' => false,
        ]);
    }
}
