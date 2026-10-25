<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\TienePublicId;
use Database\Factories\MarcaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('marcas')]
#[Fillable(['nombre', 'activa'])]
class Marca extends Model
{
    /** @use HasFactory<MarcaFactory> */
    use HasFactory, PerteneceANegocio, TienePublicId;

    /** @return HasMany<Producto, $this> */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
