<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\PerteneceASucursal;
use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Existencias de un producto en una sucursal, en la unidad de medida del producto.
 */
#[Table('stocks')]
#[Fillable(['cantidad', 'stock_minimo'])]
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory, PerteneceANegocio, PerteneceASucursal;

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'stock_minimo' => 'decimal:3'];
    }
}
