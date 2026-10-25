<?php

namespace App\Http\Resources;

use App\Models\GrupoModificador;
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
            ...$this->datosPublicos('sku', 'codigo_barras', 'nombre', 'descripcion', 'se_vende', 'es_insumo', 'unidad_venta', 'precio', 'costo', 'controla_stock', 'activo'),
            'categoria' => $this->categoria?->datosPublicos('nombre', 'activa'),
            'marca' => $this->marca?->datosPublicos('nombre', 'activa'),
            'grupos_modificadores' => $this->whenLoaded('gruposModificadores', fn () => $this->gruposModificadores
                ->map(fn (GrupoModificador $grupo) => $grupo->datosPublicos('nombre', 'tipo_seleccion', 'activo'))->all()),
        ];
    }
}
