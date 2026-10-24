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

        if ($negocio->wasRecentlyCreated && $negocio->prueba_desde === null) {
            $negocio->forceFill(['prueba_desde' => $negocio->created_at, 'prueba_hasta' => $negocio->created_at->copy()->addDays(14)])->save();
        }

        $sucursal = Sucursal::withoutGlobalScopes()->firstOrNew([
            'negocio_id' => $negocio->id,
            'nombre' => 'Casa Matriz',
        ]);

        $sucursal->forceFill([
            'negocio_id' => $negocio->id,
            'es_principal' => true,
            'activa' => true,
        ])->save();

        $sucursal->asegurarCajaInicial();
    }
}
