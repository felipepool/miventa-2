<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Los negocios existentes quedan como mixtos, con todos los módulos.
     */
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->string('rubro', 10)->default('mixto')->after('nombre');
            $table->json('modulos')->nullable()->after('rubro');
        });

        DB::table('negocios')->update(['modulos' => json_encode(['venta_por_peso', 'modificadores'])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn(['rubro', 'modulos']);
        });
    }
};
