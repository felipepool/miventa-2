<?php

namespace Database\Factories;

use App\Enums\EstadoVenta;
use App\Models\TurnoCaja;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Venta> */
class VentaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'turno_caja_id' => TurnoCaja::factory(),
            'negocio_id' => fn (array $attributes) => TurnoCaja::withoutGlobalScopes()->findOrFail($attributes['turno_caja_id'])->negocio_id,
            'sucursal_id' => fn (array $attributes) => TurnoCaja::withoutGlobalScopes()->findOrFail($attributes['turno_caja_id'])->sucursal_id,
            'user_id' => fn (array $attributes) => TurnoCaja::withoutGlobalScopes()->findOrFail($attributes['turno_caja_id'])->user_id,
            'numero' => fn (array $attributes) => (int) Venta::withoutGlobalScopes()->where('negocio_id', $attributes['negocio_id'])->max('numero') + 1,
            'numero_sucursal' => fn (array $attributes) => (int) Venta::withoutGlobalScopes()->where('sucursal_id', $attributes['sucursal_id'])->max('numero_sucursal') + 1,
            'estado' => EstadoVenta::Pagada,
            'subtotal' => 1000,
            'descuento' => 0,
            'redondeo' => 0,
            'total' => 1000,
            'pagada_at' => now(),
        ];
    }
}
