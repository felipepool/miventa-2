<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\TienePublicId;
use Database\Factories\CajaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('cajas')]
#[Fillable(['nombre', 'activa'])]
class Caja extends Model
{
    /** @use HasFactory<CajaFactory> */
    use HasFactory, PerteneceANegocio, TienePublicId;

    /** @return BelongsTo<Sucursal, $this> */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
