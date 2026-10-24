<?php

namespace App\Models;

use App\Enums\Modulo;
use App\Enums\Plan;
use Database\Factories\NegocioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property Collection<int, Modulo>|null $modulos
 * @property bool $cierre_ciego
 * @property int $descuento_maximo_cajero
 */
#[Table('negocios')]
#[Fillable(['codigo', 'nombre', 'plan', 'modulos', 'cierre_ciego', 'descuento_maximo_cajero', 'rut', 'razon_social', 'giro', 'direccion', 'telefono', 'email', 'activo'])]
class Negocio extends Model
{
    /** @use HasFactory<NegocioFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Negocio $negocio): void {
            if ($negocio->prueba_desde === null && $negocio->prueba_hasta === null) {
                $negocio->prueba_desde = $negocio->created_at ?? now();
                $negocio->prueba_hasta = $negocio->prueba_desde->addDays(14);
            }
        });
    }

    public function enPrueba(): bool
    {
        return $this->prueba_desde !== null && $this->prueba_desde->lte(now())
            && $this->prueba_hasta !== null && $this->prueba_hasta->gt(now());
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    public function adicionales(): HasMany
    {
        return $this->hasMany(NegocioAdicional::class);
    }

    public function soloLectura(): bool
    {
        return ! $this->enPrueba() && ! $this->suscripciones()->where('desde', '<=', now())
            ->where(fn ($query) => $query->whereNull('hasta')->orWhere('hasta', '>', now()))->exists();
    }

    /**
     * @return HasMany<Sucursal, $this>
     */
    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Un módulo funciona solo si el plan lo incluye y el administrador lo tiene activado.
     */
    public function tieneModulo(Modulo $modulo): bool
    {
        return $this->plan->incluye($modulo) && ($this->modulos?->contains($modulo) ?? false);
    }

    /**
     * Módulos en uso: los activados que el plan incluye.
     *
     * @return list<Modulo>
     */
    public function modulosActivos(): array
    {
        return array_values(array_filter(Modulo::cases(), fn (Modulo $modulo) => $this->tieneModulo($modulo)));
    }

    /**
     * Los códigos de negocio no distinguen mayúsculas: se guardan en minúsculas.
     */
    protected function codigo(): Attribute
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
            'prueba_desde' => 'immutable_datetime',
            'prueba_hasta' => 'immutable_datetime',
            'plan' => Plan::class,
            'modulos' => AsEnumCollection::of(Modulo::class),
            'cierre_ciego' => 'boolean',
            'descuento_maximo_cajero' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
