<?php

use App\Actions\Planes\CambioPlan;
use App\Enums\Plan;
use App\Models\Negocio;
use App\Models\NegocioAdicional;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use App\Models\User;
use Database\Seeders\TarifasSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

if (($_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION')) === 'mysql') {
    uses(DatabaseTruncation::class);
    beforeEach(function () {
        $this->seed(TarifasSeeder::class);
    });
    afterEach(function () {
        RefreshDatabaseState::$migrated = false;
    });
}

test('simultaneous acceptance opens only one plan and one additional contract on mysql', function () {
    $admin = User::factory()->for(Negocio::factory()->plan(Plan::Retail)->conSucursalPrincipal())->admin()->create();
    Tarifa::where('concepto', 'plan')->update(['precio_uf' => '1.0000']);
    $cotizacion = app(CambioPlan::class)->resumen($admin->negocio, Plan::Retail, adicionales: ['kds'])['cotizacion'];
    $payload = json_encode(['plan' => 'retail', 'adicionales' => ['kds'], 'acepto_cobro' => true, 'cotizacion' => $cotizacion], JSON_THROW_ON_ERROR);
    $directorio = sys_get_temp_dir().'/planes-concurrencia-'.bin2hex(random_bytes(8));
    mkdir($directorio);
    $codigo = <<<'PHP'
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $request = Illuminate\Http\Request::create("/api/v1/plan", "PUT", json_decode($argv[2], true), server: ["HTTP_ACCEPT" => "application/json"]);
    $app->instance("request", $request);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();
    Illuminate\Support\Facades\Auth::guard("web")->setUser(App\Models\User::findOrFail((int) $argv[1]));
    Illuminate\Support\Facades\DB::connection()->beforeExecuting(function (string $query) use ($argv): void {
        if (str_contains(strtolower($query), "for update") && str_contains($query, "negocios")) { file_put_contents($argv[3], "ready"); }
    });
    $response = $kernel->handle($request);
    echo $response->getStatusCode();
    $kernel->terminate($request, $response);
    PHP;
    $conexion = config('database.connections.mysql');
    $entorno = ['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => $conexion['host'], 'DB_PORT' => (string) $conexion['port'],
        'DB_DATABASE' => $conexion['database'], 'DB_USERNAME' => $conexion['username'], 'DB_PASSWORD' => $conexion['password'], 'DB_URL' => ''];
    $procesos = [];
    DB::beginTransaction();
    Negocio::whereKey($admin->negocio_id)->lockForUpdate()->firstOrFail();
    try {
        foreach ([0, 1] as $indice) {
            $proceso = new Process([PHP_BINARY, '-r', $codigo, (string) $admin->id, $payload, $directorio.'/'.$indice], base_path(), $entorno, timeout: 30);
            $proceso->start();
            $procesos[] = $proceso;
        }
        $limite = microtime(true) + 15;
        while (! file_exists($directorio.'/0') || ! file_exists($directorio.'/1')) {
            foreach ($procesos as $proceso) {
                if (! $proceso->isRunning()) {
                    throw new RuntimeException($proceso->getErrorOutput().$proceso->getOutput());
                }
            }
            if (microtime(true) > $limite) {
                throw new RuntimeException('Las solicitudes no alcanzaron el bloqueo del negocio.');
            }
            usleep(10000);
        }
        DB::commit();
        $estados = [];
        foreach ($procesos as $proceso) {
            $proceso->wait();
            expect($proceso->isSuccessful())->toBeTrue($proceso->getErrorOutput());
            $estados[] = (int) $proceso->getOutput();
        }
        sort($estados);
        expect($estados)->toBe([200, 422])
            ->and(Suscripcion::whereNull('hasta')->count())->toBe(1)
            ->and(NegocioAdicional::whereNull('hasta')->count())->toBe(1);
    } finally {
        foreach ($procesos as $proceso) {
            if ($proceso->isRunning()) {
                $proceso->stop();
            }
        }
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach (glob($directorio.'/*') as $archivo) {
            unlink($archivo);
        }
        rmdir($directorio);
    }
})->skip(fn () => config('database.default') !== 'mysql', 'Requiere MySQL.');
