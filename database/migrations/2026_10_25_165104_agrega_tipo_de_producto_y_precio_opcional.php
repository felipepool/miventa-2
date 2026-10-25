<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->boolean('se_vende')->default(true)->after('descripcion');
            $table->boolean('es_insumo')->default(false)->after('se_vende');
            $table->unsignedInteger('precio')->nullable()->change();
        });
    }

    /**
     * Falla si existen productos sin precio (insumos): asígnales uno antes de revertir.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->unsignedInteger('precio')->nullable(false)->change();
            $table->dropColumn(['se_vende', 'es_insumo']);
        });
    }
};
