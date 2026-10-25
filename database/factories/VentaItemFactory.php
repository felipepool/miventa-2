<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VentaItem> */
class VentaItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'venta_id' => Venta::factory(),
            'producto_id' => Producto::factory(),
            'nombre' => 'Producto',
            'unidad_venta' => 'unidad',
            'cantidad' => '1.000',
            'precio_unitario' => 1000,
            'subtotal' => 1000,
        ];
    }
}
