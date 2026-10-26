<?php

namespace App\Actions\Ventas;

use App\Actions\Inventario\RegistrarMovimientoStock;
use App\Actions\Planes\EstadoPlan;
use App\Enums\ConceptoTarifa;
use App\Enums\EstadoVenta;
use App\Models\MovimientoStock;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anula una venta pagada mientras su turno de caja sigue abierto: la venta deja de sumar al
 * esperado de ese turno y, con el adicional Inventario activo, el stock que descontó vuelve a
 * la sucursal. Una venta de un turno cerrado o con devoluciones se corrige con una devolución.
 *
 * El turno y la venta se bloquean, en ese orden, para que la anulación quede en orden con el
 * cierre del turno y dos anulaciones simultáneas dejen una sola.
 */
class AnularVenta
{
    public function __construct(
        private RegistrarMovimientoStock $stock,
        private EstadoPlan $estadoPlan,
    ) {}

    /**
     * @throws ValidationException
     */
    public function ejecutar(Venta $venta, User $usuario, string $motivo): Venta
    {
        return DB::transaction(function () use ($venta, $usuario, $motivo): Venta {
            $turno = TurnoCaja::whereKey($venta->turno_caja_id)->lockForUpdate()->firstOrFail();
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();

            if ($venta->estaAnulada()) {
                throw ValidationException::withMessages(['venta' => 'Esta venta ya está anulada.']);
            }

            if (! $turno->estaAbierto()) {
                throw ValidationException::withMessages(['venta' => 'El turno de esta venta ya se cerró. Registra una devolución en vez de anularla.']);
            }

            if ($venta->devoluciones()->exists()) {
                throw ValidationException::withMessages(['venta' => 'Esta venta tiene devoluciones. Registra otra devolución en vez de anularla.']);
            }

            $venta->forceFill([
                'estado' => EstadoVenta::Anulada,
                'anulada_at' => now(),
                'anulada_por' => $usuario->id,
                'motivo_anulacion' => $motivo,
            ])->save();

            $this->reintegrarStock($venta, $usuario);

            return $venta;
        }, 3);
    }

    /**
     * Devuelve lo que la venta descontó del stock, producto por producto y en orden fijo para
     * no cruzar bloqueos. Sin el adicional activo el stock no se toca.
     */
    private function reintegrarStock(Venta $venta, User $usuario): void
    {
        $negocio = Negocio::findOrFail($venta->negocio_id);

        if (! in_array(ConceptoTarifa::Inventario, $this->estadoPlan->adicionalesActivos($negocio), true)) {
            return;
        }

        $descontados = MovimientoStock::where('venta_id', $venta->id)->where('tipo', 'venta')
            ->selectRaw('producto_id, -SUM(cantidad) as cantidad')->groupBy('producto_id')->orderBy('producto_id')->get();

        foreach ($descontados as $descontado) {
            $producto = Producto::withoutGlobalScopes()->findOrFail($descontado->producto_id);
            $this->stock->reintegrarPorVenta($producto, $venta->sucursal, $usuario, (string) $descontado->cantidad, $venta, 'anulacion');
        }
    }
}
