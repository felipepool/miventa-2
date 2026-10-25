<?php

namespace App\Models;

use App\Enums\EstadoVenta;
use App\Enums\MedioPago;
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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    /** @return HasMany<Venta, $this> */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'turno_caja_id');
    }

    /**
     * Pagos de las ventas del turno (para sumar lo cobrado por medio de pago).
     *
     * @return HasManyThrough<Pago, Venta, $this>
     */
    public function pagos(): HasManyThrough
    {
        return $this->hasManyThrough(Pago::class, Venta::class, 'turno_caja_id');
    }

    public function estaAbierto(): bool
    {
        return $this->cerrada_at === null;
    }

    /**
     * Efectivo que debería haber en la caja: monto inicial, más ingresos y ventas en efectivo
     * (sin el vuelto), menos retiros.
     */
    public function calcularEsperado(): int
    {
        return $this->monto_inicial + $this->sumarMovimientos(TipoMovimientoCaja::Ingreso) - $this->sumarMovimientos(TipoMovimientoCaja::Retiro)
            + $this->sumarVentasEfectivo();
    }

    /** Efectivo cobrado en las ventas pagadas del turno, descontado el vuelto. */
    public function sumarVentasEfectivo(): int
    {
        return (int) Pago::query()->join('ventas', 'ventas.id', '=', 'pagos.venta_id')
            ->where('ventas.turno_caja_id', $this->id)->where('ventas.estado', EstadoVenta::Pagada->value)
            ->where('pagos.medio', MedioPago::Efectivo->value)->sum('pagos.monto');
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
