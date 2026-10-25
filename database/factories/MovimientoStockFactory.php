<?php

namespace Database\Factories;

use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MovimientoStock> */
class MovimientoStockFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'negocio_id' => fn (array $attributes) => Producto::withoutGlobalScopes()->findOrFail($attributes['producto_id'])->negocio_id,
            'sucursal_id' => fn (array $attributes) => Sucursal::factory()->state(['negocio_id' => $attributes['negocio_id']]),
            'user_id' => fn (array $attributes) => User::factory()->state(['negocio_id' => $attributes['negocio_id']]),
            'tipo' => 'entrada',
            'cantidad' => '5.000',
            'stock_resultante' => '5.000',
            'motivo' => null,
        ];
    }
}
