<?php

namespace App\Models;

use App\Enums\Permiso;
use App\Enums\Rol;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\TienePublicId;
use App\Notifications\RestablecerContrasena;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Table('users')]
#[Fillable(['name', 'username', 'email', 'password', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PerteneceANegocio, TienePublicId;

    /**
     * Sucursales asignadas explícitamente al usuario.
     *
     * @return BelongsToMany<Sucursal, $this>
     */
    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'sucursal_user');
    }

    public function esAdmin(): bool
    {
        return $this->hasRole(Rol::Admin);
    }

    /**
     * Roles del usuario dentro de su negocio. Admin es exclusivo; los roles
     * operativos (cajero, cocina, garzón) se pueden combinar.
     *
     * @return list<Rol>
     */
    public function rolesAsignados(): array
    {
        return $this->roles
            ->map(fn ($rol) => Rol::tryFrom($rol->name))
            ->filter()
            ->sortBy(fn (Rol $rol) => array_search($rol, Rol::cases(), true))
            ->values()
            ->all();
    }

    /**
     * Permisos efectivos: la unión de los permisos de todos sus roles.
     *
     * @return list<Permiso>
     */
    public function permisos(): array
    {
        return $this->getAllPermissions()
            ->map(fn ($permiso) => Permiso::tryFrom($permiso->name))
            ->filter()
            ->sortBy(fn (Permiso $permiso) => array_search($permiso, Permiso::cases(), true))
            ->values()
            ->all();
    }

    /**
     * Indica si es el único administrador activo que le queda al negocio.
     */
    public function esUltimoAdminActivo(): bool
    {
        if (! $this->activo || ! $this->esAdmin()) {
            return false;
        }

        return ! static::query()
            ->where('negocio_id', $this->negocio_id)
            ->whereKeyNot($this->getKey())
            ->where('activo', true)
            ->role(Rol::Admin->value)
            ->exists();
    }

    /**
     * Sucursales activas en las que el usuario puede trabajar: todas las del
     * negocio para un admin, o solo las asignadas para el resto.
     *
     * @return Collection<int, Sucursal>
     */
    public function sucursalesAccesibles(): Collection
    {
        $query = $this->esAdmin()
            ? Sucursal::query()->withoutGlobalScope('negocio')->where('negocio_id', $this->negocio_id)
            : $this->sucursales()->withoutGlobalScope('negocio')->where('sucursales.negocio_id', $this->negocio_id);

        return $query->activas()
            ->orderByDesc('es_principal')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Indica si el usuario y su negocio están habilitados para usar el sistema.
     */
    public function puedeIniciarSesion(): bool
    {
        return $this->activo && $this->negocio?->activo === true;
    }

    /**
     * Correo en español con el enlace para crear una contraseña nueva. A una
     * cuenta deshabilitada no se le envía: no podría ingresar igual.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        if ($this->puedeIniciarSesion()) {
            $this->notify(new RestablecerContrasena($token));
        }
    }

    /**
     * Los nombres de usuario no distinguen mayúsculas: se guardan en minúsculas.
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::lower(trim($value)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }
}
