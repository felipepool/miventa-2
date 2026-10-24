<?php

use App\Actions\Planes\CobroCajas;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
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

test('simultaneous register operations preserve one included register and exact contract counts on mysql', function (string $escenario, array $estadosEsperados, int $cajasEsperadas, int $contratosEsperados) {
    $admin = User::factory()->for(Negocio::factory()->conPlanContratado()->conSucursalPrincipal())->admin()->create();
    $sucursal = $admin->negocio->sucursales()->sole();
    $base = $sucursal->cajas()->sole();
    $objetivo = null;
    if ($escenario === 'incluida') {
        $base->forceFill(['activa' => false, 'es_incluida' => false])->save();
    } elseif ($escenario === 'reactivar') {
        $objetivo = Caja::factory()->for($sucursal)->inactiva()->create();
    } elseif ($escenario === 'desactivar') {
        $objetivo = Caja::factory()->for($sucursal)->create();
        $otra = Caja::factory()->for($sucursal)->create();
        CajaAdicional::factory()->for($objetivo)->create();
        CajaAdicional::factory()->for($otra)->create();
    }
    $cotizacion = app(CobroCajas::class)->resumen($admin->negocio, $sucursal, $objetivo)['cotizacion'];
    $directorio = sys_get_temp_dir().'/cajas-concurrencia-'.bin2hex(random_bytes(8));
    mkdir($directorio);
    $codigo = <<<'PHP'
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $request = Illuminate\Http\Request::create($argv[2], $argv[3], json_decode($argv[4], true), server: ["HTTP_ACCEPT" => "application/json"]);
    $app->instance("request", $request);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();
    Illuminate\Support\Facades\Auth::guard("web")->setUser(App\Models\User::findOrFail((int) $argv[1]));
    Illuminate\Support\Facades\DB::connection()->beforeExecuting(function (string $query) use ($argv): void {
        if (str_contains(strtolower($query), "for update") && str_contains($query, "negocios")) {
            file_put_contents($argv[5], "ready");
        }
    });
    $response = $kernel->handle($request);
    echo json_encode(["status" => $response->getStatusCode(), "body" => json_decode($response->getContent(), true)]);
    $kernel->terminate($request, $response);
    PHP;
    $conexion = config('database.connections.mysql');
    $entorno = [
        'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => $conexion['host'], 'DB_PORT' => (string) $conexion['port'],
        'DB_DATABASE' => $conexion['database'], 'DB_USERNAME' => $conexion['username'], 'DB_PASSWORD' => $conexion['password'], 'DB_URL' => '',
    ];
    $procesos = [];
    DB::beginTransaction();
    Negocio::whereKey($admin->negocio_id)->lockForUpdate()->firstOrFail();
    try {
        foreach ([0, 1] as $indice) {
            $ruta = '/api/v1/cajas';
            $metodo = 'POST';
            $payload = ['nombre' => $escenario === 'duplicada' ? 'Mesón' : 'Mesón '.$indice,
                'sucursal' => $sucursal->public_id, 'acepto_cobro' => $escenario !== 'incluida', 'cotizacion' => $cotizacion];
            if ($escenario === 'reactivar' || $escenario === 'desactivar') {
                $caja = $escenario === 'desactivar' && $indice === 0 ? $base : $objetivo;
                $ruta = '/api/v1/cajas/'.$caja->public_id.'/estado';
                $metodo = 'PATCH';
                $payload = ['activa' => $escenario === 'reactivar', 'acepto_cobro' => true, 'cotizacion' => $cotizacion];
            }
            $proceso = new Process([PHP_BINARY, '-r', $codigo, (string) $admin->id, $ruta, $metodo, json_encode($payload, JSON_THROW_ON_ERROR), $directorio.'/'.$indice], base_path(), $entorno);
            $proceso->setTimeout(30);
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
                throw new RuntimeException('Las dos solicitudes no alcanzaron el bloqueo del negocio.');
            }
            usleep(10000);
        }
        DB::commit();
        $estados = [];
        foreach ($procesos as $proceso) {
            $proceso->wait();
            expect($proceso->isSuccessful())->toBeTrue($proceso->getErrorOutput());
            $respuesta = json_decode($proceso->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $estados[] = $respuesta['status'];
        }
        sort($estados);
        expect($estados)->toBe($estadosEsperados);
        expect($sucursal->cajas()->count())->toBe($cajasEsperadas);
        expect($sucursal->cajas()->where('activa', true)->where('es_incluida', true)->count())->toBe(1);
        expect(CajaAdicional::where('negocio_id', $admin->negocio_id)->whereNull('hasta')->count())->toBe($contratosEsperados);
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
})->with([
    'one included capacity' => ['incluida', [201, 422], 2, 0],
    'stale paid quotation' => ['adicionales', [201, 422], 2, 1],
    'duplicate names' => ['duplicada', [201, 422], 2, 1],
    'same reactivation twice' => ['reactivar', [200, 200], 2, 1],
    'deactivate included and replacement' => ['desactivar', [200, 200], 3, 0],
])->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Requiere conexiones MySQL independientes.');
