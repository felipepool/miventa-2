<?php

namespace App\Http\Resources;

use App\Models\Pago;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaItemModificador;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Venta */
class VentaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->datosPublicos('subtotal', 'descuento', 'descuento_motivo', 'redondeo', 'total', 'notas'),
            'numero' => $this->numero,
            'folio' => $this->numero_sucursal,
            'estado' => $this->estado->value,
            'pagada_at' => $this->pagada_at->toIso8601String(),
            'vendedor' => $this->whenLoaded('vendedor', fn () => $this->vendedor->name),
            'items_count' => $this->whenCounted('items'),
            'pagos' => $this->whenLoaded('pagos', fn () => $this->pagos->map(fn (Pago $pago) => [
                'medio' => $pago->medio->value, 'etiqueta' => $pago->medio->etiqueta(), 'monto' => $pago->monto, 'recibido' => $pago->recibido, 'vuelto' => $pago->vuelto,
            ])->all()),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (VentaItem $item) => [
                'nombre' => $item->nombre, 'sku' => $item->sku, 'unidad_venta' => $item->unidad_venta, 'cantidad' => $item->cantidad,
                'precio_unitario' => $item->precio_unitario, 'modificadores_unitario' => $item->modificadores_unitario, 'subtotal' => $item->subtotal,
                'descuento' => $item->descuento + $item->descuento_venta, 'total' => $item->montoFinal(), 'notas' => $item->notas,
                'modificadores' => $item->modificadores->map(fn (VentaItemModificador $modificador) => [
                    'grupo' => $modificador->grupo, 'nombre' => $modificador->nombre, 'cantidad' => $modificador->cantidad,
                    'precio_adicional' => $modificador->precio_adicional, 'total' => $modificador->total,
                ])->all(),
            ])->all()),
        ];
    }
}
