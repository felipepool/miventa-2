<?php

namespace App\Http\Resources;

use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\Pago;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaItemModificador;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Comprobante interno de una venta, para imprimir o compartir. Solo lleva lo que se lee en el
 * ticket: sin ids, costos ni el nombre completo de quien vendió, porque también se entrega por
 * un enlace público.
 *
 * @mixin Venta
 */
class ComprobanteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'negocio' => [
                'nombre' => $this->negocio->nombre,
                'rut' => $this->negocio->rut,
                'direccion' => $this->sucursal->direccion ?? $this->negocio->direccion,
                'telefono' => $this->sucursal->telefono ?? $this->negocio->telefono,
            ],
            'sucursal' => $this->sucursal->nombre,
            'folio' => $this->numero_sucursal,
            'fecha' => $this->pagada_at->toIso8601String(),
            'cajero' => Str::before(trim($this->vendedor->name), ' '),
            'estado' => $this->estado->value,
            'anulada_at' => $this->anulada_at?->toIso8601String(),
            'subtotal' => $this->subtotal,
            'descuento' => $this->descuento,
            'redondeo' => $this->redondeo,
            'total' => $this->total,
            'items' => $this->items->map(fn (VentaItem $item) => [
                'nombre' => $item->nombre, 'unidad_venta' => $item->unidad_venta, 'cantidad' => $item->cantidad, 'precio_unitario' => $item->precio_unitario,
                'subtotal' => $item->subtotal, 'descuento' => $item->descuento + $item->descuento_venta, 'total' => $item->montoFinal(),
                'modificadores' => $item->modificadores->map(fn (VentaItemModificador $modificador) => [
                    'nombre' => $modificador->nombre, 'cantidad' => $modificador->cantidad, 'total' => $modificador->total,
                ])->all(),
            ])->all(),
            'pagos' => $this->pagos->map(fn (Pago $pago) => [
                'etiqueta' => $pago->medio->etiqueta(), 'monto' => $pago->monto, 'recibido' => $pago->recibido, 'vuelto' => $pago->vuelto,
            ])->all(),
            'devoluciones' => $this->devoluciones->map(fn (Devolucion $devolucion) => [
                'fecha' => $devolucion->created_at->toIso8601String(), 'etiqueta' => $devolucion->medio->etiqueta(), 'monto' => $devolucion->monto,
                'items' => $devolucion->items->map(fn (DevolucionItem $item) => [
                    'nombre' => $item->ventaItem->nombre, 'unidad_venta' => $item->ventaItem->unidad_venta, 'cantidad' => $item->cantidad,
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * Relaciones que el comprobante necesita, sin los filtros de la sesión: el enlace público se
     * abre sin sesión o con la de otro negocio.
     *
     * @return array<string, Closure>
     */
    public static function relaciones(): array
    {
        $sinFiltros = fn (Relation $relacion) => $relacion->withoutGlobalScopes();

        return [
            'negocio' => $sinFiltros, 'sucursal' => $sinFiltros, 'vendedor' => $sinFiltros, 'pagos' => $sinFiltros,
            'items.modificadores' => $sinFiltros, 'devoluciones' => $sinFiltros, 'devoluciones.items.ventaItem' => $sinFiltros,
        ];
    }
}
