<?php

namespace App\Models;

use App\Enums\TipoMovimientoCaja;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\PerteneceASucursal;
use App\Models\Concerns\TienePublicId;
use Database\Factories\TurnoCajaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de trabajo en una caja: se abre con un monto inicial y se cierra
 * contando el efectivo. Al cerrar se congelan el esperado, el contado y la
 * diferencia, para que el cierre no cambie después.
 */
#[Table('turnos_caja')]
#[Fillable(['monto_inicial', 'nota_apertura', 'nota_cierre'])]
class TurnoCaja extends Model
{
    /** @use HasFactory<TurnoCajaFactory> */
    use HasFactory, PerteneceANegocio, PerteneceASucursal, TienePublicId;

    /** @return BelongsTo<Caja, $this> */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * Usuario que abrió el turno (el único que puede operar en él).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por_id');
    }

    /** @return HasMany<MovimientoCaja, $this> */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class)->orderBy('id');
    }

    public function estaAbierto(): bool
    {
        return $this->cerrada_at === null;
    }

    /**
     * Efectivo que debería haber en la caja: monto inicial más ingresos, menos retiros.
     * Cuando existan las ventas se sumarán las ventas en efectivo.
     */
    public function calcularEsperado(): int
    {
        return $this->monto_inicial + $this->sumarMovimientos(TipoMovimientoCaja::Ingreso) - $this->sumarMovimientos(TipoMovimientoCaja::Retiro);
    }

    public function sumarMovimientos(TipoMovimientoCaja $tipo): int
    {
        return (int) $this->movimientos()->where('tipo', $tipo)->sum('monto');
    }

    /** @param Builder<TurnoCaja> $query */
    protected function scopeAbiertos(Builder $query): void
    {
        $query->whereNull('cerrada_at');
    }

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'monto_inicial' => 'integer',
            'monto_esperado' => 'integer',
            'monto_contado' => 'integer',
            'diferencia' => 'integer',
            'conteo' => 'array',
            'abierta_at' => 'datetime',
            'cerrada_at' => 'datetime',
        ];
    }
}
