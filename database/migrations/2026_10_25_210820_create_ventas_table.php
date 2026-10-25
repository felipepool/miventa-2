<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('turno_caja_id')->constrained('turnos_caja')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->unsignedInteger('numero_sucursal');
            $table->string('estado', 10)->default('pagada');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('descuento')->default(0);
            $table->string('descuento_motivo', 150)->nullable();
            $table->integer('redondeo')->default(0);
            $table->unsignedInteger('total');
            $table->string('notas', 255)->nullable();
            $table->string('clave_idempotencia', 64)->nullable();
            $table->timestamp('pagada_at');
            $table->timestamps();
            $table->unique(['negocio_id', 'numero']);
            $table->unique(['sucursal_id', 'numero_sucursal']);
            $table->unique(['negocio_id', 'clave_idempotencia']);
            $table->index(['turno_caja_id', 'estado']);
            $table->index(['sucursal_id', 'pagada_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
