<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('turno_caja_id')->constrained('turnos_caja')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('medio', 15);
            $table->unsignedInteger('monto');
            $table->string('motivo', 150);
            $table->boolean('reintegra_stock');
            $table->string('clave_idempotencia', 64)->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'clave_idempotencia']);
            $table->index(['turno_caja_id', 'medio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
