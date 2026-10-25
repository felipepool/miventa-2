<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_grupo_modificador', function (Blueprint $table): void {
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('grupo_modificador_id')->constrained('grupos_modificadores')->restrictOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->primary(['producto_id', 'grupo_modificador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_grupo_modificador');
    }
};
