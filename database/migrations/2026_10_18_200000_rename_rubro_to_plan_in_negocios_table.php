<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El rubro pasa a ser el plan contratado (mismos valores: retail, comida, mixto).
     */
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->renameColumn('rubro', 'plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->renameColumn('plan', 'rubro');
        });
    }
};
