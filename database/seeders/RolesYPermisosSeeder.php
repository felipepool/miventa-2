<?php

namespace Database\Seeders;

use App\Enums\Permiso;
use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    /**
     * Crea los permisos y los roles globales (sin negocio), que luego se
     * asignan a cada usuario dentro de su propio negocio.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $equipoAnterior = getPermissionsTeamId();
        setPermissionsTeamId(null);

        foreach (Permiso::cases() as $permiso) {
            Permission::findOrCreate($permiso->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Rol::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web')->syncPermissions(
                array_map(fn (Permiso $permiso) => $permiso->value, $rol->permisos()),
            );
        }

        setPermissionsTeamId($equipoAnterior);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
