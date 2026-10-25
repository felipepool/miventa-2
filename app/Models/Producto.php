<?php

namespace App\Models;

use App\Enums\UnidadVenta;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\TienePublicId;
use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('productos')]
#[Fillable(['sku', 'codigo_barras', 'nombre', 'descripcion', 'se_vende', 'es_insumo', 'unidad_venta', 'precio', 'costo', 'controla_stock', 'activo'])]
class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory, PerteneceANegocio, TienePublicId;

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /** @return BelongsTo<Marca, $this> */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    protected function casts(): array
    {
        return [
            'se_vende' => 'boolean',
            'es_insumo' => 'boolean',
            'unidad_venta' => UnidadVenta::class,
            'precio' => 'integer',
            'costo' => 'integer',
            'controla_stock' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
