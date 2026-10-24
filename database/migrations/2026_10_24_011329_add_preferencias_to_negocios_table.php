<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferencias de operación: cierre ciego de caja y tope de descuento del cajero (%).
     */
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->boolean('cierre_ciego')->default(true)->after('modulos');
            $table->unsignedTinyInteger('descuento_maximo_cajero')->default(10)->after('cierre_ciego');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn(['cierre_ciego', 'descuento_maximo_cajero']);
        });
    }
};
