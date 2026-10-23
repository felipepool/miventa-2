<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\TienePublicId;
use Database\Factories\SucursalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Table('sucursales')]
#[Fillable(['nombre', 'direccion', 'telefono', 'es_principal', 'activa'])]
class Sucursal extends Model
{
    /** @use HasFactory<SucursalFactory> */
    use HasFactory, PerteneceANegocio, TienePublicId;

    protected static function booted(): void
    {
        static::created(fn (Sucursal $sucursal) => $sucursal->asegurarCajaInicial());
    }

    /** @return HasMany<Caja, $this> */
    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    public function asegurarCajaInicial(): void
    {
        DB::transaction(function (): void {
            self::withoutGlobalScopes()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            if (! $this->cajas()->withoutGlobalScopes()->exists()) {
                $caja = new Caja(['nombre' => 'Caja 1']);
                $caja->forceFill(['negocio_id' => $this->negocio_id, 'sucursal_id' => $this->id])->save();
            }
        });
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sucursal_user');
    }

    /**
     * @param  Builder<Sucursal>  $query
     */
    protected function scopeActivas(Builder $query): void
    {
        $query->where('activa', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'activa' => 'boolean',
        ];
    }
}
