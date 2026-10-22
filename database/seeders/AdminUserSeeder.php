<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public const string USERNAME = 'admin';

    /**
     * Create or update the `admin` user of the demo business, using the
     * password (and optional name/email) defined in the ADMIN_* env values.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('No se puede crear el administrador demo en producción.');
        }

        $password = config('app.admin.password');

        if (blank($password)) {
            throw new RuntimeException('Define ADMIN_PASSWORD en el archivo .env antes de ejecutar este seeder.');
        }

        $this->call([RolesYPermisosSeeder::class, NegocioDemoSeeder::class]);

        $negocio = Negocio::where('codigo', NegocioDemoSeeder::CODIGO)->firstOrFail();

        $admin = User::firstOrNew([
            'negocio_id' => $negocio->id,
            'username' => self::USERNAME,
        ]);

        $admin->forceFill([
            'name' => config('app.admin.name'),
            'email' => config('app.admin.email') ?: null,
            'password' => $password,
            'email_verified_at' => $admin->email_verified_at ?? now(),
            'activo' => true,
        ])->save();

        $equipoAnterior = getPermissionsTeamId();
        setPermissionsTeamId($negocio->id);
        $admin->unsetRelation('roles')->syncRoles([Rol::Admin->value]);
        setPermissionsTeamId($equipoAnterior);
    }
}
