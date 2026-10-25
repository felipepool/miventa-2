<?php

namespace Database\Factories;

use App\Enums\UnidadVenta;
use App\Models\Categoria;
use App\Models\Negocio;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Producto> */
class ProductoFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'categoria_id' => fn (array $attributes) => Categoria::factory()->state(['negocio_id' => $attributes['negocio_id']]),
            'marca_id' => null,
            'sku' => null,
            'codigo_barras' => null,
            'nombre' => 'Producto '.fake()->unique()->bothify('##??'),
            'descripcion' => null,
            'unidad_venta' => UnidadVenta::Unidad,
            'precio' => fake()->numberBetween(5, 200) * 100,
            'costo' => null,
            'controla_stock' => true,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }
}
