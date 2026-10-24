<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

test('migration dates existing trials from creation and leaves contracts untouched', function () {
    $this->freezeTime();
    $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertSuccessful();
    $ids = [];
    foreach (['vencido' => now()->subDays(20), 'reciente' => now()->subDays(3), 'contratado' => now()->subDays(90)] as $codigo => $creacion) {
        $ids[$codigo] = DB::table('negocios')->insertGetId([
            'codigo' => $codigo, 'nombre' => $codigo, 'plan' => 'retail', 'modulos' => '[]',
            'created_at' => $creacion, 'updated_at' => $creacion,
        ]);
    }
    $tarifa = DB::table('tarifas')->where('concepto', 'plan')->where('plan', 'retail')->value('id');
    DB::table('suscripciones')->insert([
        'negocio_id' => $ids['contratado'], 'tarifa_id' => $tarifa, 'plan' => 'retail',
        'precio_uf' => '0.7000', 'desde' => now()->subDays(90), 'hasta' => null,
    ]);
    $antes = DB::table('suscripciones')->first();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    foreach (['vencido', 'reciente'] as $codigo) {
        $fila = DB::table('negocios')->where('id', $ids[$codigo])->first();
        expect(CarbonImmutable::parse($fila->prueba_hasta)->equalTo(CarbonImmutable::parse($fila->created_at)->addDays(14)))->toBeTrue();
    }
    expect(CarbonImmutable::parse(DB::table('negocios')->where('id', $ids['vencido'])->value('prueba_hasta'))->isPast())->toBeTrue()
        ->and(DB::table('negocios')->where('id', $ids['contratado'])->value('prueba_hasta'))->toBeNull()
        ->and(DB::table('suscripciones')->first())->toEqual($antes);
});
