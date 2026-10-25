<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Inventario se cobra como adicional: 0,5 UF mensuales + IVA en cada plan.
     */
    public function up(): void
    {
        foreach (['retail', 'comida', 'mixto'] as $plan) {
            DB::table('tarifas')->insertOrIgnore(['public_id' => Str::lower((string) Str::ulid()), 'concepto' => 'inventario',
                'plan' => $plan, 'precio_uf' => '0.5000', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * Falla si hay contrataciones del adicional: primero deben cerrarse.
     */
    public function down(): void
    {
        DB::table('tarifas')->where('concepto', 'inventario')->delete();
    }
};
