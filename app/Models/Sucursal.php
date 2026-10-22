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

#[Table('sucursales')]
#[Fillable(['nombre', 'direccion', 'telefono', 'es_principal', 'activa'])]
class Sucursal extends Model
{
    /** @use HasFactory<SucursalFactory> */
    use HasFactory, PerteneceANegocio, TienePublicId;

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
