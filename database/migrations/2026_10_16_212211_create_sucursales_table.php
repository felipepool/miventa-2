<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['negocio_id', 'nombre']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
