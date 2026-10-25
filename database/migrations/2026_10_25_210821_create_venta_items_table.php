<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('sku', 30)->nullable();
            $table->string('unidad_venta', 10);
            $table->decimal('cantidad', 10, 3);
            $table->unsignedInteger('precio_unitario');
            $table->unsignedInteger('costo_unitario')->nullable();
            $table->unsignedInteger('modificadores_unitario')->default(0);
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('descuento')->default(0);
            $table->unsignedInteger('descuento_venta')->default(0);
            $table->string('notas', 255)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->index(['venta_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_items');
    }
};
