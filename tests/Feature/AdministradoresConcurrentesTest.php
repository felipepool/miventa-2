<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

if (($_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION')) === 'mysql') {
    uses(DatabaseTruncation::class);

    afterEach(function () {
        RefreshDatabaseState::$migrated = false;
    });
}

test('concurrent deactivations and demotions retain an active administrator on mysql', function (string $primera, string $segunda) {
    $admin = User::factory()->admin()->create();
    $otroAdmin = User::factory()->for($admin->negocio)->admin()->create(['username' => 'otro.admin']);
    $editor = User::factory()->for($admin->negocio)->cajero()->create();
    setPermissionsTeamId($admin->negocio_id);
    $editor->givePermissionTo('gestionar_usuarios');
    $principal = $admin->negocio->sucursales()->first();
    $directorio = sys_get_temp_dir().'/miventa-concurrencia-'.bin2hex(random_bytes(8));
    mkdir($directorio);

    DB::unprepared('CREATE TRIGGER ralentizar_actualizacion_usuario BEFORE UPDATE ON users FOR EACH ROW DO SLEEP(0.4)');

    $codigo = <<<'PHP'
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $request = Illuminate\Http\Request::create($argv[2], $argv[3], json_decode($argv[4], true), server: ["HTTP_ACCEPT" => "application/json"]);
    $app->instance("request", $request);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();
    Illuminate\Support\Facades\Auth::guard("web")->setUser(App\Models\User::findOrFail((int) $argv[1]));
    file_put_contents($argv[5], "ready");
    $limite = microtime(true) + 15;
    while (!file_exists($argv[6])) {
        if (microtime(true) > $limite) { exit(2); }
        usleep(10000);
    }
    $response = $kernel->handle($request);
    echo json_encode(["status" => $response->getStatusCode(), "body" => json_decode($response->getContent(), true)]);
    $kernel->terminate($request, $response);
    PHP;

    $conexion = config('database.connections.mysql');
    $entorno = [
        'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => $conexion['host'], 'DB_PORT' => (string) $conexion['port'],
        'DB_DATABASE' => $conexion['database'], 'DB_USERNAME' => $conexion['username'],
        'DB_PASSWORD' => $conexion['password'], 'DB_URL' => '',
    ];
    $procesos = [];

    try {
        foreach ([[$admin, $primera], [$otroAdmin, $segunda]] as $indice => [$usuario, $accion]) {
            $ruta = '/api/v1/usuarios/'.$usuario->public_id;
            $payload = $accion === 'desactivar' ? ['activo' => false] : [
                'name' => 'Usuario actualizado '.$indice, 'username' => $usuario->username,
                'roles' => ['cajero'], 'sucursales' => [$principal->public_id],
            ];
            $proceso = new Process([
                PHP_BINARY, '-r', $codigo, (string) $editor->id,
                $ruta.($accion === 'desactivar' ? '/estado' : ''), $accion === 'desactivar' ? 'PATCH' : 'PUT',
                json_encode($payload, JSON_THROW_ON_ERROR), $directorio.'/listo-'.$indice, $directorio.'/iniciar',
            ], base_path(), $entorno, timeout: 30);
            $proceso->start();
            $procesos[] = $proceso;
        }

        $limite = microtime(true) + 15;
        while (! file_exists($directorio.'/listo-0') || ! file_exists($directorio.'/listo-1')) {
            foreach ($procesos as $proceso) {
                if (! $proceso->isRunning()) {
                    throw new RuntimeException($proceso->getErrorOutput().$proceso->getOutput());
                }
            }

            if (microtime(true) > $limite) {
                throw new RuntimeException('Los procesos de prueba no llegaron al punto de sincronización.');
            }

            usleep(10000);
        }

        file_put_contents($directorio.'/iniciar', 'go');
        $estados = [];

        foreach ($procesos as $proceso) {
            $proceso->wait();
            expect($proceso->isSuccessful())->toBeTrue($proceso->getErrorOutput());
            $respuesta = json_decode($proceso->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $estados[] = $respuesta['status'];

            if ($respuesta['status'] === 422) {
                expect($respuesta['body']['message'])->toBe('El negocio debe tener al menos un administrador activo.');
            }
        }

        sort($estados);
        expect($estados)->toBe([200, 422]);
        expect(User::where('negocio_id', $admin->negocio_id)->where('activo', true)->role('admin')->count())->toBe(1);
    } finally {
        foreach ($procesos as $proceso) {
            if ($proceso->isRunning()) {
                $proceso->stop();
            }
        }

        DB::unprepared('DROP TRIGGER IF EXISTS ralentizar_actualizacion_usuario');
        foreach (glob($directorio.'/*') as $archivo) {
            unlink($archivo);
        }
        rmdir($directorio);
    }
})->with([
    'deactivate both' => ['desactivar', 'desactivar'],
    'demote both' => ['degradar', 'degradar'],
    'deactivate and demote' => ['desactivar', 'degradar'],
])->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Esta prueba verifica bloqueo real entre conexiones MySQL.');
