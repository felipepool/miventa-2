<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos_caja', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->unsignedInteger('monto_inicial');
            $table->string('nota_apertura', 255)->nullable();
            $table->timestamp('abierta_at');
            $table->timestamp('cerrada_at')->nullable();
            $table->foreignId('cerrada_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('nota_cierre', 255)->nullable();
            $table->integer('monto_esperado')->nullable();
            $table->unsignedInteger('monto_contado')->nullable();
            $table->integer('diferencia')->nullable();
            $table->json('conteo')->nullable();
            $table->timestamps();
            $table->unique(['caja_id', 'numero']);
            $table->index(['caja_id', 'cerrada_at']);
            $table->index(['user_id', 'cerrada_at']);
            $table->index(['sucursal_id', 'abierta_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos_caja');
    }
};
