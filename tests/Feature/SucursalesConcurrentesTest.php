<?php

use App\Actions\Planes\CobroSucursales;
use App\Models\Caja;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\SucursalAdicional;
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

test('simultaneous branch requests preserve included capacity and contract counts on mysql', function (string $escenario, array $estadosEsperados, int $sucursalesEsperadas, int $contratosEsperados) {
    $factory = Negocio::factory()->conPlanContratado();
    if ($escenario !== 'incluida') {
        $factory = $factory->conSucursalPrincipal();
    }
    $admin = User::factory()->for($factory)->admin()->create();
    $objetivo = $escenario === 'reactivar' ? Sucursal::factory()->for($admin->negocio)->inactiva()->create() : null;
    $cotizacion = app(CobroSucursales::class)->resumen($admin->negocio, $objetivo)['cotizacion'];
    $directorio = sys_get_temp_dir().'/miventa-sucursales-'.bin2hex(random_bytes(8));
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
            $payload = $objetivo ? ['activa' => true] : ['nombre' => $escenario === 'duplicada' ? 'Norte' : 'Sucursal '.$indice];
            $payload = [...$payload, 'acepto_cobro' => $escenario !== 'incluida', 'cotizacion' => $cotizacion];
            $proceso = new Process([
                PHP_BINARY, '-r', $codigo, (string) $admin->id,
                $objetivo ? '/api/v1/sucursales/'.$objetivo->public_id.'/estado' : '/api/v1/sucursales', $objetivo ? 'PATCH' : 'POST',
                json_encode($payload, JSON_THROW_ON_ERROR), $directorio.'/listo-'.$indice,
            ], base_path(), $entorno, timeout: 30);
            $proceso->start();
            $procesos[] = $proceso;
        }
        $limite = microtime(true) + 15;
        while ($escenario !== 'incluida' && (! file_exists($directorio.'/listo-0') || ! file_exists($directorio.'/listo-1'))) {
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
        expect(Sucursal::where('negocio_id', $admin->negocio_id)->count())->toBe($sucursalesEsperadas);
        expect(Caja::where('negocio_id', $admin->negocio_id)->count())->toBe($sucursalesEsperadas);
        expect(SucursalAdicional::where('negocio_id', $admin->negocio_id)->whereNull('hasta')->count())->toBe($contratosEsperados);
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
    'no branch selected' => ['incluida', [409, 409], 0, 0],
    'two paid branches' => ['adicionales', [201, 201], 3, 2],
    'duplicate names' => ['duplicada', [201, 422], 2, 1],
    'same reactivation twice' => ['reactivar', [200, 200], 2, 1],
])->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Requiere conexiones MySQL independientes.');
