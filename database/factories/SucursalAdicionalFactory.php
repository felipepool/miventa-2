<?php

namespace Database\Factories;

use App\Enums\ConceptoTarifa;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\SucursalAdicional;
use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SucursalAdicional> */
class SucursalAdicionalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sucursal_id' => Sucursal::factory(),
            'negocio_id' => fn (array $attributes) => Sucursal::withoutGlobalScopes()->findOrFail($attributes['sucursal_id'])->negocio_id,
            'tarifa_id' => fn (array $attributes) => Tarifa::where('concepto', ConceptoTarifa::Sucursal)
                ->where('plan', Negocio::findOrFail($attributes['negocio_id'])->plan)->firstOrFail()->id,
            'desde' => now(), 'hasta' => null,
        ];
    }
}
