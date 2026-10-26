<?php

use App\Models\Caja;
use App\Models\Devolucion;
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
 * Lanza dos anulaciones o dos devoluciones de la misma venta en procesos distintos mientras esta
 * prueba retiene el bloqueo del turno; al soltarlo compiten por la venta y solo una puede pasar.
 */
test('simultaneous cancellations or refunds of the same sale apply only once on mysql', function (string $caso) {
    $negocio = Negocio::factory()->conPlanContratado()->conSucursalPrincipal()->create();
    $admin = User::factory()->for($negocio)->admin()->create();
    $turno = TurnoCaja::factory()->create(['caja_id' => Caja::where('negocio_id', $negocio->id)->sole()->id, 'user_id' => $admin->id]);
    $producto = Producto::factory()->for($negocio)->create(['precio' => 1000, 'se_vende' => true, 'controla_stock' => false]);
    $id = $this->actingAs($admin)->postJson('/api/v1/ventas', ['items' => [['producto' => $producto->public_id, 'cantidad' => 2]], 'pagos' => [['medio' => 'debito', 'monto' => 2000]]])
        ->assertCreated()->json('data.id');
    [$ruta, $cuerpo] = $caso === 'anular'
        ? ["/api/v1/ventas/$id/anular", ['motivo' => 'Error de cobro']]
        : ["/api/v1/ventas/$id/devoluciones", ['items' => [['linea' => 0, 'cantidad' => 2]], 'medio' => 'debito', 'motivo' => 'Cliente arrepentido', 'reintegrar_stock' => false]];
    $conClave = in_array($caso, ['reintento', 'otra_venta'], true);
    $segundaRuta = $ruta;
    if ($conClave) {
        $cuerpo['clave_idempotencia'] = 'devolucion-concurrente';
    }
    if ($caso === 'otra_venta') {
        $otraId = $this->postJson('/api/v1/ventas', ['items' => [['producto' => $producto->public_id, 'cantidad' => 2]], 'pagos' => [['medio' => 'debito', 'monto' => 2000]]])
            ->assertCreated()->json('data.id');
        $segundaRuta = "/api/v1/ventas/$otraId/devoluciones";
    }
    $directorio = sys_get_temp_dir().'/correcciones-concurrencia-'.bin2hex(random_bytes(8));
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
        if (str_contains(strtolower($query), "for update") && str_contains($query, $argv[5])) {
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
    if ($conClave) {
        Negocio::whereKey($negocio->id)->lockForUpdate()->firstOrFail();
    } else {
        TurnoCaja::whereKey($turno->id)->lockForUpdate()->firstOrFail();
    }
    try {
        foreach ([0, 1] as $indice) {
            $proceso = new Process([PHP_BINARY, '-r', $codigo, (string) $admin->id, $indice === 0 ? $ruta : $segundaRuta, json_encode($cuerpo, JSON_THROW_ON_ERROR), $directorio.'/'.$indice, $conClave ? '`negocios`' : '`turnos_caja`'], base_path(), $entorno);
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
                throw new RuntimeException('Las dos solicitudes no alcanzaron el bloqueo del turno.');
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
        expect($estados)->toBe(match ($caso) {
            'anular' => [200, 422],
            'reintento' => [200, 201],
            'otra_venta' => [201, 409],
            default => [201, 422],
        });
        expect($caso === 'anular' ? Venta::withoutGlobalScopes()->where('estado', 'anulada')->count() : Devolucion::withoutGlobalScopes()->count())->toBe(1);
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
})->with(['cancellations' => ['anular'], 'refunds' => ['devolver'], 'same request' => ['reintento'], 'same key on different sales' => ['otra_venta']])
    ->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Requiere conexiones MySQL independientes.');
