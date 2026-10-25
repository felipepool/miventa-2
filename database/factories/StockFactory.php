<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Stock;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stock> */
class StockFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'negocio_id' => fn (array $attributes) => Producto::withoutGlobalScopes()->findOrFail($attributes['producto_id'])->negocio_id,
            'sucursal_id' => fn (array $attributes) => Sucursal::factory()->state(['negocio_id' => $attributes['negocio_id']]),
            'cantidad' => '10.000',
            'stock_minimo' => '0.000',
        ];
    }
}
