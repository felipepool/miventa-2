<?php

namespace Database\Seeders;

use App\Actions\Planes\CobroCajas;
use App\Actions\Planes\CobroSucursales;
use App\Enums\Plan;
use App\Enums\Rol;
use App\Models\Caja;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NegocioMultisucursalSeeder extends Seeder
{
    public const string CODIGO = 'demo-multi';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('No se puede crear el negocio multisucursal demo en producción.');
        }

        $password = config('app.admin.password');
        if (blank($password)) {
            throw new RuntimeException('Define ADMIN_PASSWORD antes de ejecutar este seeder.');
        }

        DB::transaction(function () use ($password): void {
            $this->call([RolesYPermisosSeeder::class, TarifasSeeder::class]);
            $negocio = Negocio::firstOrCreate(['codigo' => self::CODIGO], [
                'nombre' => 'Negocio Demo Multisucursal',
                'plan' => Plan::Mixto,
                'modulos' => Plan::Mixto->modulos(),
            ]);

            $admin = User::firstOrCreate([
                'negocio_id' => $negocio->id,
                'username' => 'admin',
            ], [
                'name' => 'Administrador Multisucursal',
                'password' => $password,
                'activo' => true,
            ]);

            $equipoAnterior = getPermissionsTeamId();
            try {
                setPermissionsTeamId($negocio->id);
                $admin->unsetRelation('roles')->syncRoles([Rol::Admin->value]);
            } finally {
                setPermissionsTeamId($equipoAnterior);
            }

            foreach ([
                ['nombre' => 'Casa Matriz', 'direccion' => 'Dirección de demostración 100', 'es_principal' => true],
                ['nombre' => 'Sucursal Norte', 'direccion' => 'Dirección de demostración 200', 'es_principal' => false],
                ['nombre' => 'Sucursal Sur', 'direccion' => 'Dirección de demostración 300', 'es_principal' => false],
            ] as $datos) {
                $sucursal = Sucursal::withoutGlobalScopes()->firstOrCreate([
                    'negocio_id' => $negocio->id,
                    'nombre' => $datos['nombre'],
                ], [...$datos, 'activa' => true]);
                $sucursal->asegurarCajaInicial();

                if ($sucursal->wasRecentlyCreated) {
                    app(CobroSucursales::class)->contratar($negocio, $sucursal, $admin);
                }

                $adicionales = $datos['es_principal'] ? ['Caja 2', 'Caja 3'] : ($datos['nombre'] === 'Sucursal Norte' ? ['Caja 2'] : []);
                foreach ($adicionales as $nombre) {
                    $caja = Caja::withoutGlobalScopes()->firstOrCreate([
                        'negocio_id' => $negocio->id, 'sucursal_id' => $sucursal->id, 'nombre' => $nombre,
                    ], ['activa' => true]);
                    if ($caja->wasRecentlyCreated) {
                        app(CobroCajas::class)->contratar($negocio, $sucursal, $caja, $admin);
                    }
                }
            }
        });
    }
}
