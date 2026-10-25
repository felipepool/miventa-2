<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->unique(['negocio_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
