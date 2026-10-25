<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('turno_caja_id')->constrained('turnos_caja')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->unsignedInteger('monto');
            $table->string('motivo', 150);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['turno_caja_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
