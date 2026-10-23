<?php

namespace Database\Factories;

use App\Enums\ConceptoTarifa;
use App\Models\Negocio;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Suscripcion> */
class SuscripcionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'tarifa_id' => fn (array $attributes) => Tarifa::where('concepto', ConceptoTarifa::Plan)
                ->where('plan', Negocio::findOrFail($attributes['negocio_id'])->plan)->firstOrFail()->id,
            'desde' => now(), 'hasta' => null,
        ];
    }
}
