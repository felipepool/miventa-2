<?php

namespace Database\Seeders;

use App\Enums\Plan;
use App\Models\Negocio;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class NegocioDemoSeeder extends Seeder
{
    public const string CODIGO = 'demo';

    public const string NOMBRE = 'Negocio Demo';

    /**
     * Crea el negocio de demostración con su sucursal principal.
     */
    public function run(): void
    {
        $negocio = Negocio::firstOrCreate(['codigo' => self::CODIGO], [
            'nombre' => self::NOMBRE,
            'plan' => Plan::Mixto,
            'modulos' => Plan::Mixto->modulos(),
        ]);

        $sucursal = Sucursal::withoutGlobalScopes()->firstOrNew([
            'negocio_id' => $negocio->id,
            'nombre' => 'Casa Matriz',
        ]);

        $sucursal->forceFill([
            'negocio_id' => $negocio->id,
            'es_principal' => true,
            'activa' => true,
        ])->save();
    }
}
