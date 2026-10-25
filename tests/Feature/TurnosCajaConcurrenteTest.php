<?php

use App\Models\Caja;
use App\Models\Negocio;
use App\Models\TurnoCaja;
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

/**
 * Lanza dos aperturas de turno en procesos distintos mientras esta prueba retiene el bloqueo
 * de `$tabla`; al soltarlo ambas compiten y solo una puede abrir el turno.
 */
test('two simultaneous shift openings leave exactly one open shift on mysql', function (string $caso) {
    $negocio = Negocio::factory()->conPlanContratado()->conSucursalPrincipal()->create();
    $cajaUno = Caja::where('negocio_id', $negocio->id)->sole();
    $cajaDos = Caja::factory()->create(['sucursal_id' => $cajaUno->sucursal_id]);
    [$ana, $luis] = [User::factory()->for($negocio)->cajero()->create(), User::factory()->for($negocio)->cajero()->create()];
    [$tabla, $solicitudes] = $caso === 'same box'
        ? ['cajas', [[$ana, $cajaUno], [$luis, $cajaUno]]]
        : ['users', [[$ana, $cajaUno], [$ana, $cajaDos]]];
    $directorio = sys_get_temp_dir().'/turnos-concurrencia-'.bin2hex(random_bytes(8));
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
        if (str_contains(strtolower($query), "for update") && str_contains($query, "`".$argv[5]."`")) {
            file_put_contents($argv[4], "ready");
        }
    });
    $response = $kernel->handle($request);
    echo json_encode(["status" => $response->getStatusCode()]);
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
    $tabla === 'cajas' ? Caja::whereKey($cajaUno->id)->lockForUpdate()->firstOrFail() : User::whereKey($ana->id)->lockForUpdate()->firstOrFail();
    try {
        foreach ($solicitudes as $indice => [$usuario, $caja]) {
            $payload = json_encode(['caja' => $caja->public_id, 'monto_inicial' => 1000], JSON_THROW_ON_ERROR);
            $proceso = new Process([PHP_BINARY, '-r', $codigo, (string) $usuario->id, '/api/v1/caja/abrir', $payload, $directorio.'/'.$indice, $tabla], base_path(), $entorno);
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
                throw new RuntimeException('Las dos solicitudes no alcanzaron el bloqueo de '.$tabla.'.');
            }
            usleep(10000);
        }
        DB::commit();
        $estados = [];
        foreach ($procesos as $proceso) {
            $proceso->wait();
            expect($proceso->isSuccessful())->toBeTrue($proceso->getErrorOutput());
            $estados[] = json_decode($proceso->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'];
        }
        sort($estados);
        expect($estados)->toBe([201, 422]);
        expect(TurnoCaja::count())->toBe(1);
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
})->with(['same box' => ['same box'], 'same user' => ['same user']])
    ->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Requiere conexiones MySQL independientes.');
