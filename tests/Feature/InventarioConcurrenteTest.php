<?php

use App\Models\MovimientoStock;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Stock;
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

test('two simultaneous exits never leave the stock negative on mysql', function () {
    $admin = User::factory()->for(Negocio::factory()->conPlanContratado()->conSucursalPrincipal())->admin()->create();
    $sucursal = $admin->negocio->sucursales()->where('es_principal', true)->sole();
    $producto = Producto::factory()->for($admin->negocio)->create();
    Stock::factory()->create(['producto_id' => $producto->id, 'negocio_id' => $admin->negocio_id, 'sucursal_id' => $sucursal->id, 'cantidad' => '10.000']);
    $directorio = sys_get_temp_dir().'/inventario-concurrencia-'.bin2hex(random_bytes(8));
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
        if (str_contains(strtolower($query), "for update") && str_contains($query, "stocks")) {
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
    $ruta = '/api/v1/inventario/'.$producto->public_id.'/movimientos';
    $payload = json_encode(['tipo' => 'salida', 'cantidad' => 6, 'motivo' => 'Merma'], JSON_THROW_ON_ERROR);
    $procesos = [];
    DB::beginTransaction();
    Stock::withoutGlobalScopes()->where('producto_id', $producto->id)->lockForUpdate()->firstOrFail();
    try {
        foreach ([0, 1] as $indice) {
            $proceso = new Process([PHP_BINARY, '-r', $codigo, (string) $admin->id, $ruta, $payload, $directorio.'/'.$indice], base_path(), $entorno);
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
                throw new RuntimeException('Las dos solicitudes no alcanzaron el bloqueo del stock.');
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
        expect(MovimientoStock::count())->toBe(1)->and(Stock::sole()->cantidad)->toBe('4.000');
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
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Requiere conexiones MySQL independientes.');
