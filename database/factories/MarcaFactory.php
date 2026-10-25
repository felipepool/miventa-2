<?php

namespace Database\Factories;

use App\Models\Marca;
use App\Models\Negocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Marca> */
class MarcaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'nombre' => 'Marca '.fake()->unique()->bothify('##??'),
            'activa' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['activa' => false]);
    }
}
