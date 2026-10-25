<?php

namespace App\Models;

use App\Models\Concerns\TienePublicId;
use Database\Factories\ModificadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opción de un grupo de modificadores (ej. «Pan amasado», «Ketchup»), sin costo
 * adicional o con el que defina el usuario. Se aísla por negocio a través de su grupo.
 */
#[Table('modificadores')]
#[Fillable(['nombre', 'precio_adicional', 'cantidad_maxima', 'orden', 'activo'])]
class Modificador extends Model
{
    /** @use HasFactory<ModificadorFactory> */
    use HasFactory, TienePublicId;

    /** @return BelongsTo<GrupoModificador, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoModificador::class, 'grupo_modificador_id');
    }

    protected function casts(): array
    {
        return [
            'precio_adicional' => 'integer',
            'cantidad_maxima' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
