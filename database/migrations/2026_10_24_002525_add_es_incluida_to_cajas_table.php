<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table): void {
            $table->boolean('es_incluida')->default(false);
        });

        DB::table('sucursales')->orderBy('id')->each(function (object $sucursal): void {
            $cajaId = DB::table('cajas')->where('sucursal_id', $sucursal->id)->where('activa', true)->min('id');
            if ($cajaId !== null) {
                DB::table('cajas')->where('id', $cajaId)->update(['es_incluida' => true]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table): void {
            $table->dropColumn('es_incluida');
        });
    }
};
