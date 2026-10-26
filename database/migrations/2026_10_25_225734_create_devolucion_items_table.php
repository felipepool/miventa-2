<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucion_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('venta_item_id')->constrained('venta_items')->restrictOnDelete();
            $table->decimal('cantidad', 10, 3);
            $table->unsignedInteger('monto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_items');
    }
};
