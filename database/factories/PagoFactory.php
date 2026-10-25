<?php

namespace Database\Factories;

use App\Enums\MedioPago;
use App\Models\Pago;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pago> */
class PagoFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['venta_id' => Venta::factory(), 'medio' => MedioPago::Efectivo, 'monto' => 1000, 'recibido' => 1000, 'vuelto' => 0];
    }
}
