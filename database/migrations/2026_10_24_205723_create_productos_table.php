<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->restrictOnDelete();
            $table->string('sku', 30)->nullable();
            $table->string('codigo_barras', 50)->nullable();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('unidad_venta', 10);
            $table->unsignedInteger('precio');
            $table->unsignedInteger('costo')->nullable();
            $table->boolean('controla_stock')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['negocio_id', 'sku']);
            $table->unique(['negocio_id', 'codigo_barras']);
            $table->index(['negocio_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
