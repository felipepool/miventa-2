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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** Grupos de modificadores que se ofrecen al vender el producto, en el orden definido. @return BelongsToMany<GrupoModificador, $this> */
    public function gruposModificadores(): BelongsToMany
    {
        return $this->belongsToMany(GrupoModificador::class, 'producto_grupo_modificador')->withPivot('orden')->orderByPivot('orden');
    }

    /** Existencias en la sucursal actual (el modelo Stock ya filtra por sucursal). @return HasOne<Stock, $this> */
    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
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
