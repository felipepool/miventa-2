<?php

namespace App\Models\Concerns;

use App\Models\Negocio;
use App\Support\Contexto\NegocioActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla el modelo por negocio: filtra todas las consultas por el negocio actual
 * y asigna `negocio_id` automáticamente al crear registros.
 *
 * Sin negocio actual (consola, seeders, invitados) no se aplica el filtro.
 *
 * @mixin Model
 */
trait PerteneceANegocio
{
    public static function bootPerteneceANegocio(): void
    {
        static::addGlobalScope('negocio', function (Builder $query): void {
            $negocioActual = app(NegocioActual::class);

            if ($negocioActual->existe()) {
                $query->where($query->qualifyColumn('negocio_id'), $negocioActual->id());
            }
        });

        static::creating(function (Model $model): void {
            $negocioActual = app(NegocioActual::class);

            if ($negocioActual->existe()) {
                $model->setAttribute('negocio_id', $negocioActual->id());
            }
        });
    }

    /**
     * @return BelongsTo<Negocio, $this>
     */
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }
}
