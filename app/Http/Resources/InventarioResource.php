<?php

namespace App\Http\Resources;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Producto */
class InventarioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $cantidad = $this->stock?->cantidad ?? '0.000';
        $minimo = $this->stock?->stock_minimo ?? '0.000';

        return [
            ...$this->datosPublicos('sku', 'codigo_barras', 'nombre', 'unidad_venta', 'activo'),
            'cantidad' => $cantidad,
            'stock_minimo' => $minimo,
            'stock_bajo' => bccomp($minimo, '0', 3) > 0 && bccomp($cantidad, $minimo, 3) <= 0,
        ];
    }
}
