<?php

namespace App\Models;

use Database\Factories\DevolucionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cantidad devuelta de una línea de la venta y el monto que se reembolsa por ella. */
#[Table('devolucion_items')]
#[Fillable(['venta_item_id', 'cantidad', 'monto'])]
class DevolucionItem extends Model
{
    /** @use HasFactory<DevolucionItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<Devolucion, $this> */
    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    /** @return BelongsTo<VentaItem, $this> */
    public function ventaItem(): BelongsTo
    {
        return $this->belongsTo(VentaItem::class);
    }

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'monto' => 'integer'];
    }
}
