<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\PerteneceASucursal;
use App\Models\Concerns\TienePublicId;
use Database\Factories\MovimientoStockFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Historial de inventario: cada registro es definitivo y no se edita ni se elimina.
 * `cantidad` es la variación con signo (negativa en las salidas).
 */
#[Table('movimientos_stock')]
class MovimientoStock extends Model
{
    /** @use HasFactory<MovimientoStockFactory> */
    use HasFactory, PerteneceANegocio, PerteneceASucursal, TienePublicId;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de stock no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de stock no se pueden eliminar.'));
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'stock_resultante' => 'decimal:3', 'created_at' => 'datetime'];
    }
}
