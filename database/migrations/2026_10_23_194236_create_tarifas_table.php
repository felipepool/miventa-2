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
        Schema::create('tarifas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('concepto', 15);
            $table->string('plan', 10);
            $table->decimal('precio_uf', 8, 4)->nullable();
            $table->timestamps();
            $table->unique(['concepto', 'plan']);
        });

        foreach (['retail', 'comida', 'mixto'] as $plan) {
            foreach (['plan', 'sucursal', 'caja'] as $concepto) {
                DB::table('tarifas')->insert([
                    'public_id' => Str::lower((string) Str::ulid()), 'concepto' => $concepto, 'plan' => $plan,
                    'precio_uf' => $concepto === 'plan' ? null : '0.2500',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas');
    }
};
