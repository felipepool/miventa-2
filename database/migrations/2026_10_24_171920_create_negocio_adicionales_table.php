<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negocio_adicionales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('tarifa_id')->constrained('tarifas');
            $table->string('concepto', 15);
            $table->string('plan', 10);
            $table->decimal('precio_uf', 8, 4);
            $table->timestamp('desde');
            $table->timestamp('hasta')->nullable();
            $table->foreignId('aceptado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aceptado_at');
            $table->timestamps();
            $table->index(['negocio_id', 'concepto', 'hasta']);
        });
        foreach (['retail', 'comida', 'mixto'] as $plan) {
            foreach (['documentos', 'mesas', 'kds'] as $concepto) {
                DB::table('tarifas')->insertOrIgnore(['public_id' => Str::lower((string) Str::ulid()), 'concepto' => $concepto,
                    'plan' => $plan, 'precio_uf' => '0.5000', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('negocio_adicionales');
    }
};
