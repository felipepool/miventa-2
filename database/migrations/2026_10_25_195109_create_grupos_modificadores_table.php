<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_modificadores', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('referencia', 100)->nullable();
            $table->string('tipo_seleccion', 10);
            $table->unsignedSmallInteger('minimo')->default(0);
            $table->unsignedSmallInteger('maximo')->nullable();
            $table->unsignedSmallInteger('cantidad_gratis')->nullable();
            $table->unsignedInteger('precio_extra')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['negocio_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_modificadores');
    }
};
