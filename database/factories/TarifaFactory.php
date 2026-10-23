<?php

namespace Database\Factories;

use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tarifa> */
class TarifaFactory extends Factory
{
    public function definition(): array
    {
        return ['concepto' => ConceptoTarifa::Plan, 'plan' => Plan::Retail, 'precio_uf' => '0.5000'];
    }
}
