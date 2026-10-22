<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory()->conSucursalPrincipal(),
            'name' => fake()->name(),
            'username' => fake()->unique()->bothify('usuario.####'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'activo' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /**
     * Administrador del negocio, con el nombre de usuario por defecto `admin`.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'username' => 'admin',
        ])->conRol(Rol::Admin);
    }

    /**
     * Cajero asignado a la sucursal principal de su negocio.
     */
    public function cajero(): static
    {
        return $this->conRol(Rol::Cajero)->conSucursalPrincipal();
    }

    /**
     * Usuario de cocina asignado a la sucursal principal de su negocio.
     */
    public function cocina(): static
    {
        return $this->conRol(Rol::Cocina)->conSucursalPrincipal();
    }

    public function garzon(): static
    {
        return $this->conRol(Rol::Garzon)->conSucursalPrincipal();
    }

    /**
     * Usuario con varios roles operativos (ej. cajero y cocina), asignado a la
     * sucursal principal de su negocio.
     */
    public function conRoles(Rol ...$roles): static
    {
        $factory = $this->conSucursalPrincipal();

        foreach ($roles as $rol) {
            $factory = $factory->conRol($rol);
        }

        return $factory;
    }

    private function conSucursalPrincipal(): static
    {
        return $this->afterCreating(function (User $user): void {
            $sucursalPrincipal = $user->negocio?->sucursales()->withoutGlobalScopes()->where('es_principal', true)->first();

            if ($sucursalPrincipal !== null) {
                $user->sucursales()->attach($sucursalPrincipal);
            }
        });
    }

    private function conRol(Rol $rol): static
    {
        return $this->afterCreating(function (User $user) use ($rol): void {
            $equipoAnterior = getPermissionsTeamId();

            setPermissionsTeamId($user->negocio_id);
            $user->assignRole($rol->value);
            setPermissionsTeamId($equipoAnterior);

            $user->unsetRelation('roles')->unsetRelation('permissions');
        });
    }
}
