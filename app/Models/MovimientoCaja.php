<?php

namespace App\Models;

use App\Enums\TipoMovimientoCaja;
use App\Models\Concerns\TienePublicId;
use Database\Factories\MovimientoCajaFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ingreso o retiro de efectivo de un turno. Es definitivo: un error se corrige
 * con otro movimiento, nunca editando o eliminando el anterior.
 */
#[Table('movimientos_caja')]
class MovimientoCaja extends Model
{
    /** @use HasFactory<MovimientoCajaFactory> */
    use HasFactory, TienePublicId;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de caja no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de caja no se pueden eliminar.'));
    }

    /** @return BelongsTo<TurnoCaja, $this> */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return ['tipo' => TipoMovimientoCaja::class, 'monto' => 'integer', 'created_at' => 'datetime'];
    }
}
