<?php

namespace Database\Factories;

use App\Enums\TipoMovimientoCaja;
use App\Models\MovimientoCaja;
use App\Models\TurnoCaja;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MovimientoCaja> */
class MovimientoCajaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'turno_caja_id' => TurnoCaja::factory(),
            'user_id' => fn (array $attributes) => TurnoCaja::withoutGlobalScopes()->findOrFail($attributes['turno_caja_id'])->user_id,
            'tipo' => TipoMovimientoCaja::Ingreso,
            'monto' => 5000,
            'motivo' => 'Sencillo',
        ];
    }
}
