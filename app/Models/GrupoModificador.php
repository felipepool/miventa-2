<?php

namespace App\Models;

use App\Enums\TipoSeleccion;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\TienePublicId;
use Database\Factories\GrupoModificadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo de opciones que se ofrecen al vender un producto (tipo de pan,
 * agregados, salsas…), con sus reglas de selección y cobro.
 */
#[Table('grupos_modificadores')]
#[Fillable(['nombre', 'referencia', 'tipo_seleccion', 'minimo', 'maximo', 'cantidad_gratis', 'precio_extra', 'activo'])]
class GrupoModificador extends Model
{
    /** @use HasFactory<GrupoModificadorFactory> */
    use HasFactory, PerteneceANegocio, TienePublicId;

    /** @return HasMany<Modificador, $this> */
    public function modificadores(): HasMany
    {
        return $this->hasMany(Modificador::class)->orderBy('orden')->orderBy('id');
    }

    /** @return BelongsToMany<Producto, $this> */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_grupo_modificador')->withPivot('orden');
    }

    protected function casts(): array
    {
        return [
            'tipo_seleccion' => TipoSeleccion::class,
            'minimo' => 'integer',
            'maximo' => 'integer',
            'cantidad_gratis' => 'integer',
            'precio_extra' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
