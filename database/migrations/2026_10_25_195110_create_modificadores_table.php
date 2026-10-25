<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modificadores', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('grupo_modificador_id')->constrained('grupos_modificadores')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->unsignedInteger('precio_adicional')->default(0);
            $table->unsignedSmallInteger('cantidad_maxima')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modificadores');
    }
};
