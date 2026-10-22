<?php

namespace App\Models\Concerns;

use App\Models\Sucursal;
use App\Support\Contexto\SucursalActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla el modelo por sucursal: filtra todas las consultas por la sucursal
 * activa y asigna `sucursal_id` automáticamente al crear registros.
 *
 * Para datos operativos (stock, caja, ventas). Sin sucursal activa no se aplica el filtro.
 *
 * @mixin Model
 */
trait PerteneceASucursal
{
    public static function bootPerteneceASucursal(): void
    {
        static::addGlobalScope('sucursal', function (Builder $query): void {
            $sucursalActual = app(SucursalActual::class);

            if ($sucursalActual->existe()) {
                $query->where($query->qualifyColumn('sucursal_id'), $sucursalActual->id());
            }
        });

        static::creating(function (Model $model): void {
            $sucursalActual = app(SucursalActual::class);

            if ($sucursalActual->existe()) {
                $model->setAttribute('sucursal_id', $sucursalActual->id());
            }
        });
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
