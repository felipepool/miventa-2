<?php

namespace App\Http\Resources;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Producto */
class ProductoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->datosPublicos('sku', 'codigo_barras', 'nombre', 'descripcion', 'unidad_venta', 'precio', 'costo', 'controla_stock', 'activo'),
            'categoria' => $this->categoria?->datosPublicos('nombre', 'activa'),
            'marca' => $this->marca?->datosPublicos('nombre', 'activa'),
        ];
    }
}
