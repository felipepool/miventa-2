<?php

namespace App\Http\Resources;

use App\Models\Devolucion;
use App\Models\DevolucionItem;
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
        $devueltas = $this->relationLoaded('devoluciones') ? $this->cantidadesDevueltas() : null;

        return [
            ...$this->datosPublicos('subtotal', 'descuento', 'descuento_motivo', 'redondeo', 'total', 'notas', 'motivo_anulacion'),
            'numero' => $this->numero,
            'folio' => $this->numero_sucursal,
            'estado' => $this->estado->value,
            'pagada_at' => $this->pagada_at->toIso8601String(),
            'anulada_at' => $this->anulada_at?->toIso8601String(),
            'anulada_por' => $this->whenLoaded('anuladaPor', fn () => $this->anuladaPor?->name),
            'turno_abierto' => $this->whenLoaded('turno', fn () => $this->turno->estaAbierto()),
            'devolucion' => $this->estadoDeDevolucion($devueltas),
            'total_devuelto' => $this->when($devueltas !== null || isset($this->total_devuelto), fn () => (int) ($this->total_devuelto ?? $this->devoluciones->sum('monto'))),
            'vendedor' => $this->whenLoaded('vendedor', fn () => $this->vendedor->name),
            'items_count' => $this->whenCounted('items'),
            'pagos' => $this->whenLoaded('pagos', fn () => $this->pagos->map(fn (Pago $pago) => [
                'medio' => $pago->medio->value, 'etiqueta' => $pago->medio->etiqueta(), 'monto' => $pago->monto, 'recibido' => $pago->recibido, 'vuelto' => $pago->vuelto,
            ])->all()),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (VentaItem $item) => [
                'linea' => $item->orden, 'nombre' => $item->nombre, 'sku' => $item->sku, 'unidad_venta' => $item->unidad_venta, 'cantidad' => $item->cantidad,
                'precio_unitario' => $item->precio_unitario, 'modificadores_unitario' => $item->modificadores_unitario, 'subtotal' => $item->subtotal,
                'descuento' => $item->descuento + $item->descuento_venta, 'total' => $item->montoFinal(), 'notas' => $item->notas,
                'devuelta' => $devueltas === null ? null : ($devueltas[$item->id] ?? '0.000'),
                'modificadores' => $item->modificadores->map(fn (VentaItemModificador $modificador) => [
                    'grupo' => $modificador->grupo, 'nombre' => $modificador->nombre, 'cantidad' => $modificador->cantidad,
                    'precio_adicional' => $modificador->precio_adicional, 'total' => $modificador->total,
                ])->all(),
            ])->all()),
            'devoluciones' => $this->whenLoaded('devoluciones', fn () => $this->devoluciones->map(fn (Devolucion $devolucion) => [
                ...$devolucion->datosPublicos('monto', 'motivo', 'reintegra_stock'),
                'medio' => $devolucion->medio->value, 'etiqueta' => $devolucion->medio->etiqueta(),
                'usuario' => $devolucion->usuario->name, 'created_at' => $devolucion->created_at->toIso8601String(),
                'items' => $devolucion->items->map(fn (DevolucionItem $item) => [
                    'nombre' => $item->ventaItem->nombre, 'unidad_venta' => $item->ventaItem->unidad_venta, 'cantidad' => $item->cantidad, 'monto' => $item->monto,
                ])->all(),
            ])->all()),
        ];
    }

    /**
     * Estado de las devoluciones: desde las devoluciones cargadas (detalle) o desde las sumas del listado.
     *
     * @param  array<int, string>|null  $devueltas
     */
    private function estadoDeDevolucion(?array $devueltas): ?string
    {
        if ($devueltas !== null && $this->relationLoaded('items')) {
            $vendida = $this->items->reduce(fn (string $suma, VentaItem $item) => bcadd($suma, $item->cantidad, 3), '0');

            return Venta::estadoDevolucion($vendida, array_reduce($devueltas, fn (string $suma, string $cantidad) => bcadd($suma, $cantidad, 3), '0'));
        }

        if (isset($this->cantidad_vendida)) {
            return Venta::estadoDevolucion((string) $this->cantidad_vendida, (string) ($this->cantidad_devuelta ?? '0'));
        }

        return null;
    }
}
