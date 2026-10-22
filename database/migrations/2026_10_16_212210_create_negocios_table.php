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
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre');
            $table->string('rut', 12)->nullable()->unique();
            $table->string('razon_social')->nullable();
            $table->string('giro')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('negocios');
    }
};
