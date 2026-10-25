<?php

namespace Database\Factories;

use App\Enums\TipoSeleccion;
use App\Models\GrupoModificador;
use App\Models\Negocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GrupoModificador> */
class GrupoModificadorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'nombre' => 'Grupo '.fake()->unique()->bothify('##??'),
            'referencia' => null,
            'tipo_seleccion' => TipoSeleccion::Multiple,
            'minimo' => 0,
            'maximo' => null,
            'cantidad_gratis' => null,
            'precio_extra' => 0,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }
}
