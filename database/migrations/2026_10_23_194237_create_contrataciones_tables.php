<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['suscripciones', 'sucursales_adicionales', 'cajas_adicionales'] as $nombre) {
            Schema::create($nombre, function (Blueprint $table) use ($nombre): void {
                $table->id();
                $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
                $table->foreignId('tarifa_id')->constrained('tarifas');
                if ($nombre === 'sucursales_adicionales') {
                    $table->foreignId('sucursal_id')->constrained('sucursales');
                }
                if ($nombre === 'cajas_adicionales') {
                    $table->foreignId('caja_id')->constrained('cajas');
                }
                $table->string('plan', 10);
                $table->decimal('precio_uf', 8, 4);
                $table->timestamp('desde');
                $table->timestamp('hasta')->nullable();
                $table->foreignId('aceptado_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('aceptado_at')->nullable();
                $table->timestamps();
                $table->index(['negocio_id', 'hasta']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas_adicionales');
        Schema::dropIfExists('sucursales_adicionales');
        Schema::dropIfExists('suscripciones');
    }
};
