<?php

use App\Models\Caja;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
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

/**
 * Lanza dos ventas en procesos distintos mientras esta prueba retiene el bloqueo del negocio;
 * al soltarlo compiten por los folios y por la clave de idempotencia.
 */
test('simultaneous sales get distinct folios and a repeated key creates one sale on mysql', function (bool $mismaClave) {
    $negocio = Negocio::factory()->conPlanContratado()->conSucursalPrincipal()->create();
    $cajero = User::factory()->for($negocio)->cajero()->create();
    TurnoCaja::factory()->create(['caja_id' => Caja::where('negocio_id', $negocio->id)->sole()->id, 'user_id' => $cajero->id]);
    $producto = Producto::factory()->for($negocio)->create(['precio' => 1000, 'se_vende' => true, 'controla_stock' => false]);
    $directorio = sys_get_temp_dir().'/ventas-concurrencia-'.bin2hex(random_bytes(8));
    mkdir($directorio);
    $codigo = <<<'PHP'
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $request = Illuminate\Http\Request::create($argv[2], "POST", json_decode($argv[3], true), server: ["HTTP_ACCEPT" => "application/json"]);
    $app->instance("request", $request);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();
    Illuminate\Support\Facades\Auth::guard("web")->setUser(App\Models\User::findOrFail((int) $argv[1]));
    Illuminate\Support\Facades\DB::connection()->beforeExecuting(function (string $query) use ($argv): void {
        if (str_contains(strtolower($query), "for update") && str_contains($query, "`negocios`")) {
            file_put_contents($argv[4], "ready");
        }
    });
    $response = $kernel->handle($request);
    echo json_encode(["status" => $response->getStatusCode(), "folio" => $response->getStatusCode() < 300 ? json_decode($response->getContent(), true)["data"]["folio"] : null]);
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
    Negocio::whereKey($negocio->id)->lockForUpdate()->firstOrFail();
    try {
        foreach ([0, 1] as $indice) {
            $payload = json_encode([
                'items' => [['producto' => $producto->public_id, 'cantidad' => 1]], 'pagos' => [['medio' => 'debito', 'monto' => 1000]],
                'clave_idempotencia' => $mismaClave ? 'misma-clave' : 'clave-'.$indice,
            ], JSON_THROW_ON_ERROR);
            $proceso = new Process([PHP_BINARY, '-r', $codigo, (string) $cajero->id, '/api/v1/ventas', $payload, $directorio.'/'.$indice], base_path(), $entorno);
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
        $respuestas = [];
        foreach ($procesos as $proceso) {
            $proceso->wait();
            expect($proceso->isSuccessful())->toBeTrue($proceso->getErrorOutput());
            $respuestas[] = json_decode($proceso->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        $estados = array_column($respuestas, 'status');
        sort($estados);
        $folios = array_column($respuestas, 'folio');
        sort($folios);
        if ($mismaClave) {
            expect($estados)->toBe([200, 201])->and($folios)->toBe([1, 1])->and(Venta::withoutGlobalScopes()->count())->toBe(1);
        } else {
            expect($estados)->toBe([201, 201])->and($folios)->toBe([1, 2])->and(Venta::withoutGlobalScopes()->count())->toBe(2);
        }
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
})->with(['same key' => [true], 'different keys' => [false]])
    ->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Requiere conexiones MySQL independientes.');
