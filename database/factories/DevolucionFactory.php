<?php

namespace Database\Factories;

use App\Enums\MedioPago;
use App\Models\Devolucion;
use App\Models\TurnoCaja;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Devolucion> */
class DevolucionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'venta_id' => Venta::factory(),
            'turno_caja_id' => fn (array $attributes) => Venta::withoutGlobalScopes()->findOrFail($attributes['venta_id'])->turno_caja_id,
            'negocio_id' => fn (array $attributes) => TurnoCaja::withoutGlobalScopes()->findOrFail($attributes['turno_caja_id'])->negocio_id,
            'sucursal_id' => fn (array $attributes) => TurnoCaja::withoutGlobalScopes()->findOrFail($attributes['turno_caja_id'])->sucursal_id,
            'user_id' => fn (array $attributes) => TurnoCaja::withoutGlobalScopes()->findOrFail($attributes['turno_caja_id'])->user_id,
            'medio' => MedioPago::Efectivo,
            'monto' => 1000,
            'motivo' => 'Producto con falla',
            'reintegra_stock' => true,
        ];
    }
}
