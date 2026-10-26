<?php

namespace Database\Factories;

use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\VentaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DevolucionItem> */
class DevolucionItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['devolucion_id' => Devolucion::factory(), 'venta_item_id' => VentaItem::factory(), 'cantidad' => '1.000', 'monto' => 1000];
    }
}
