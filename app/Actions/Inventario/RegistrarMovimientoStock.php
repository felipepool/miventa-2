<?php

namespace App\Actions\Inventario;

use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Aplica un movimiento al stock de un producto en una sucursal y lo deja en el
 * historial. La fila de stock se bloquea durante la operación para que dos
 * movimientos simultáneos no se pisen ni dejen el stock en negativo.
 */
class RegistrarMovimientoStock
{
    public const array TIPOS = ['entrada', 'salida', 'ajuste'];

    private const int ESCALA = 3;

    /**
     * @param  string  $cantidad  Cantidad movida; en un ajuste, el stock real contado.
     */
    public function ejecutar(Producto $producto, Sucursal $sucursal, User $usuario, string $tipo, string $cantidad, ?string $motivo): MovimientoStock
    {
        return DB::transaction(function () use ($producto, $sucursal, $usuario, $tipo, $cantidad, $motivo): MovimientoStock {
            $stock = $this->bloquearStock($producto, $sucursal);
            $anterior = $stock->cantidad;
            $nuevoStock = match ($tipo) {
                'entrada' => bcadd($stock->cantidad, $cantidad, self::ESCALA),
                'salida' => bcsub($stock->cantidad, $cantidad, self::ESCALA),
                default => bcadd($cantidad, '0', self::ESCALA),
            };

            if (bccomp($nuevoStock, '0', self::ESCALA) < 0) {
                throw ValidationException::withMessages(['cantidad' => 'No hay stock suficiente. Disponible: '.$this->formatear($anterior).'.']);
            }

            $stock->update(['cantidad' => $nuevoStock]);

            $movimiento = new MovimientoStock;
            $movimiento->forceFill([
                'negocio_id' => $producto->negocio_id,
                'sucursal_id' => $sucursal->id,
                'producto_id' => $producto->id,
                'user_id' => $usuario->id,
                'tipo' => $tipo,
                'cantidad' => bcsub($nuevoStock, $anterior, self::ESCALA),
                'stock_resultante' => $nuevoStock,
                'motivo' => $motivo,
            ])->save();

            return $movimiento->refresh();
        }, 3);
    }

    public function fijarMinimo(Producto $producto, Sucursal $sucursal, string $minimo): Stock
    {
        return DB::transaction(function () use ($producto, $sucursal, $minimo): Stock {
            $stock = $this->bloquearStock($producto, $sucursal);
            $stock->update(['stock_minimo' => bcadd($minimo, '0', self::ESCALA)]);

            return $stock;
        }, 3);
    }

    /**
     * Crea la fila de stock si no existe (una sola, aun con solicitudes simultáneas)
     * y la devuelve bloqueada.
     */
    private function bloquearStock(Producto $producto, Sucursal $sucursal): Stock
    {
        $bloquear = fn (): ?Stock => Stock::withoutGlobalScopes()->where('sucursal_id', $sucursal->id)
            ->where('producto_id', $producto->id)->lockForUpdate()->first();

        $stock = $bloquear();

        if ($stock === null) {
            Stock::withoutGlobalScopes()->insertOrIgnore([
                'negocio_id' => $producto->negocio_id,
                'sucursal_id' => $sucursal->id,
                'producto_id' => $producto->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stock = $bloquear();
        }

        return $stock ?? throw new RuntimeException('No se pudo bloquear el stock del producto.');
    }

    private function formatear(string $cantidad): string
    {
        return rtrim(rtrim($cantidad, '0'), '.') ?: '0';
    }
}
