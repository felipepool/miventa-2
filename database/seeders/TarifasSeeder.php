<?php

namespace Database\Seeders;

use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Tarifa;
use Illuminate\Database\Seeder;

class TarifasSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Plan::cases() as $plan) {
            foreach (ConceptoTarifa::cases() as $concepto) {
                Tarifa::firstOrCreate(['concepto' => $concepto->value, 'plan' => $plan->value], [
                    'precio_uf' => $concepto === ConceptoTarifa::Plan ? null : (in_array($concepto, ConceptoTarifa::adicionales(), true) ? '0.5000' : '0.2500'),
                ]);
            }
        }
    }
}
