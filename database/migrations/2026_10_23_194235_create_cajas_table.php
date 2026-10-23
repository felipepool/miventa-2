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
        Schema::create('cajas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('nombre', 50);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->unique(['sucursal_id', 'nombre']);
        });

        DB::table('sucursales')->orderBy('id')->each(function (object $sucursal): void {
            DB::table('cajas')->insert([
                'public_id' => Str::lower((string) Str::ulid()), 'negocio_id' => $sucursal->negocio_id,
                'sucursal_id' => $sucursal->id, 'nombre' => 'Caja 1', 'activa' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
