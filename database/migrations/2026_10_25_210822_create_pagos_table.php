<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->string('medio', 15);
            $table->unsignedInteger('monto');
            $table->unsignedInteger('recibido')->nullable();
            $table->unsignedInteger('vuelto')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['venta_id', 'medio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
