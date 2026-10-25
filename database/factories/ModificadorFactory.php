<?php

namespace Database\Factories;

use App\Models\GrupoModificador;
use App\Models\Modificador;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Modificador> */
class ModificadorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'grupo_modificador_id' => GrupoModificador::factory(),
            'nombre' => 'Opción '.fake()->unique()->bothify('##??'),
            'precio_adicional' => 0,
            'cantidad_maxima' => null,
            'orden' => 0,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }
}
