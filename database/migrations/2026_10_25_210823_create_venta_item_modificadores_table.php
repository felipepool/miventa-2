<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_item_modificadores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_item_id')->constrained('venta_items')->cascadeOnDelete();
            $table->foreignId('modificador_id')->constrained('modificadores')->restrictOnDelete();
            $table->string('grupo', 100);
            $table->string('nombre', 100);
            $table->unsignedSmallInteger('cantidad');
            $table->unsignedInteger('precio_adicional');
            $table->unsignedInteger('total');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_item_modificadores');
    }
};
