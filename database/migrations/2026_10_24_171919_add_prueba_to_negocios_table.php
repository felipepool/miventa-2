<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table): void {
            $table->timestamp('prueba_desde')->nullable();
            $table->timestamp('prueba_hasta')->nullable();
        });
        DB::table('negocios')->whereNotExists(function ($query): void {
            $query->selectRaw('1')->from('suscripciones')->whereColumn('suscripciones.negocio_id', 'negocios.id');
        })->orderBy('id')->chunkById(200, function ($negocios): void {
            foreach ($negocios as $negocio) {
                $desde = CarbonImmutable::parse($negocio->created_at);
                DB::table('negocios')->where('id', $negocio->id)->update(['prueba_desde' => $desde, 'prueba_hasta' => $desde->addDays(14)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table): void {
            $table->dropColumn(['prueba_desde', 'prueba_hasta']);
        });
    }
};
