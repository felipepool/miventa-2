<?php

namespace App\Models;

use App\Enums\MedioPago;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de una venta. `monto` es lo que cubre del total; en efectivo, `recibido` es lo que
 * entregó el cliente y `vuelto` la diferencia, que no entra a la caja.
 */
#[Table('pagos')]
#[Fillable(['medio', 'monto', 'recibido', 'vuelto'])]
class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Venta, $this> */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    protected function casts(): array
    {
        return ['medio' => MedioPago::class, 'monto' => 'integer', 'recibido' => 'integer', 'vuelto' => 'integer'];
    }
}
