<?php

namespace App\Models;

use Database\Factories\VentaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Línea de una venta, con el nombre, SKU, unidad, precio y costo del producto al vender. */
#[Table('venta_items')]
#[Fillable(['producto_id', 'nombre', 'sku', 'unidad_venta', 'cantidad', 'precio_unitario', 'costo_unitario', 'modificadores_unitario', 'subtotal', 'descuento', 'descuento_venta', 'notas', 'orden'])]
class VentaItem extends Model
{
    /** @use HasFactory<VentaItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<Venta, $this> */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** @return HasMany<VentaItemModificador, $this> */
    public function modificadores(): HasMany
    {
        return $this->hasMany(VentaItemModificador::class)->orderBy('id');
    }

    /** Monto final de la línea, ya descontados los descuentos de la línea y el repartido de la venta. */
    public function montoFinal(): int
    {
        return $this->subtotal - $this->descuento - $this->descuento_venta;
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'integer',
            'costo_unitario' => 'integer',
            'modificadores_unitario' => 'integer',
            'subtotal' => 'integer',
            'descuento' => 'integer',
            'descuento_venta' => 'integer',
            'orden' => 'integer',
        ];
    }
}
