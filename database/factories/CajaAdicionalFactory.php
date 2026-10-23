<?php

namespace Database\Factories;

use App\Enums\ConceptoTarifa;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CajaAdicional> */
class CajaAdicionalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'caja_id' => Caja::factory(),
            'negocio_id' => fn (array $attributes) => Caja::withoutGlobalScopes()->findOrFail($attributes['caja_id'])->negocio_id,
            'tarifa_id' => fn (array $attributes) => Tarifa::where('concepto', ConceptoTarifa::Caja)
                ->where('plan', Negocio::findOrFail($attributes['negocio_id'])->plan)->firstOrFail()->id,
            'desde' => now(), 'hasta' => null,
        ];
    }
}
