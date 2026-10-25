<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Modificador elegido en una línea de venta, con su grupo y precio al momento de vender. */
#[Table('venta_item_modificadores')]
#[Fillable(['modificador_id', 'grupo', 'nombre', 'cantidad', 'precio_adicional', 'total'])]
class VentaItemModificador extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<VentaItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(VentaItem::class, 'venta_item_id');
    }

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'precio_adicional' => 'integer', 'total' => 'integer'];
    }
}
