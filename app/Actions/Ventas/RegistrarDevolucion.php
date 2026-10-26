<?php

namespace App\Actions\Ventas;

use App\Actions\Inventario\RegistrarMovimientoStock;
use App\Actions\Planes\EstadoPlan;
use App\Enums\ConceptoTarifa;
use App\Enums\MedioPago;
use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\MovimientoStock;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Support\Ventas\Redondeo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la devolución de productos de una venta, total o por líneas y cantidades. El monto lo
 * calcula el servidor como la parte proporcional de lo cobrado por cada línea y sale del turno
 * abierto de quien devuelve, por el medio elegido; en efectivo se redondea a $10.
 *
 * El turno de quien devuelve y la venta se bloquean, en ese orden: dos devoluciones simultáneas
 * no superan lo vendido y el efectivo devuelto queda en orden con el cierre del turno.
 */
class RegistrarDevolucion
{
    private const int ESCALA = 3;

    public function __construct(
        private RegistrarMovimientoStock $stock,
        private EstadoPlan $estadoPlan,
    ) {}

    /**
     * @param  array{items: list<array{linea: int, cantidad: string|int|float}>, medio: string, motivo: string, reintegrar_stock: bool, clave_idempotencia?: string|null}  $datos
     *
     * @throws ValidationException
     */
    public function ejecutar(Venta $venta, User $usuario, array $datos): Devolucion
    {
        $turnoAbierto = TurnoCaja::abiertos()->where('user_id', $usuario->id)->first()
            ?? throw ValidationException::withMessages(['turno' => 'Abre tu caja para registrar una devolución.']);

        return DB::transaction(function () use ($venta, $usuario, $datos, $turnoAbierto): Devolucion {
            $clave = $datos['clave_idempotencia'] ?? null;

            // La clave es única por negocio, incluso entre ventas, cajas y sucursales distintas.
            if ($clave !== null) {
                Negocio::whereKey($venta->negocio_id)->lockForUpdate()->firstOrFail();
            }

            $turno = TurnoCaja::whereKey($turnoAbierto->id)->lockForUpdate()->firstOrFail();
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();

            if ($clave !== null && $existente = Devolucion::withoutGlobalScope('sucursal')
                ->where('negocio_id', $venta->negocio_id)->where('clave_idempotencia', $clave)->lockForUpdate()->first()) {
                $this->validarRepeticion($existente, $venta, $usuario, $datos);

                return $existente;
            }

            if (! $turno->estaAbierto()) {
                throw ValidationException::withMessages(['turno' => 'Tu caja ya fue cerrada. Ábrela de nuevo para registrar la devolución.']);
            }

            if ($venta->estaAnulada()) {
                throw ValidationException::withMessages(['venta' => 'Esta venta está anulada: no tiene nada que devolver.']);
            }

            $medio = MedioPago::from($datos['medio']);
            $lineas = $this->lineas($venta, $datos['items']);
            $monto = array_sum(array_column($lineas, 'monto'));
            $porDevolver = max(0, $venta->total - (int) $venta->devoluciones()->sum('monto'));

            if ($medio === MedioPago::Efectivo) {
                $redondeado = Redondeo::efectivo($monto);
                $monto = $redondeado <= $porDevolver ? $redondeado : intdiv($porDevolver, 10) * 10;
            } else {
                $monto = min($monto, $porDevolver);
            }

            if ($monto < 1) {
                throw ValidationException::withMessages(['items' => 'Elige al menos un producto con monto a devolver.']);
            }

            $this->repartirMonto($lineas, $monto);

            if ($medio === MedioPago::Efectivo && $monto > $turno->calcularEsperado()) {
                throw ValidationException::withMessages(['medio' => 'No hay suficiente efectivo en tu caja para esta devolución. Disponible: $'.number_format($turno->calcularEsperado(), 0, ',', '.').'.']);
            }

            $devolucion = new Devolucion;
            $devolucion->forceFill([
                'negocio_id' => $venta->negocio_id,
                'sucursal_id' => $venta->sucursal_id,
                'venta_id' => $venta->id,
                'turno_caja_id' => $turno->id,
                'user_id' => $usuario->id,
                'medio' => $medio,
                'monto' => $monto,
                'motivo' => $datos['motivo'],
                'reintegra_stock' => $datos['reintegrar_stock'],
                'clave_idempotencia' => $clave,
            ])->save();

            $devolucion->items()->createMany(array_map(fn (array $linea) => [
                'venta_item_id' => $linea['item']->id, 'cantidad' => $linea['cantidad'], 'monto' => $linea['monto'],
            ], $lineas));

            if ($datos['reintegrar_stock']) {
                $this->reintegrarStock($venta, $usuario, $lineas);
            }

            return $devolucion->refresh();
        }, 3);
    }

    /**
     * Compara la solicitud con lo registrado, sin depender del orden o formato de las cantidades.
     *
     * @param  array{items: list<array{linea: int, cantidad: string|int|float}>, medio: string, motivo: string, reintegrar_stock: bool, clave_idempotencia?: string|null}  $datos
     */
    private function validarRepeticion(Devolucion $existente, Venta $venta, User $usuario, array $datos): void
    {
        $mensaje = 'Esta clave de idempotencia ya se utilizó para otra solicitud.';
        abort_if($existente->venta_id !== $venta->id || $existente->user_id !== $usuario->id
            || $existente->medio->value !== $datos['medio'] || $existente->motivo !== $datos['motivo']
            || $existente->reintegra_stock !== (bool) $datos['reintegrar_stock'], 409, $mensaje);

        $registradas = $existente->items()->with('ventaItem')->get()->map(fn (DevolucionItem $item) => [
            'linea' => $item->ventaItem->orden, 'cantidad' => bcadd($item->cantidad, '0', self::ESCALA),
        ])->sortBy('linea')->values()->all();
        $pedidas = collect($datos['items'])->map(fn (array $item) => [
            'linea' => (int) $item['linea'], 'cantidad' => bcadd((string) $item['cantidad'], '0', self::ESCALA),
        ])->sortBy('linea')->values()->all();

        abort_if($registradas !== $pedidas, 409, $mensaje);
    }

    /**
     * Valida cada línea pedida contra lo vendido menos lo ya devuelto y calcula su monto: la parte
     * proporcional de lo cobrado por la línea, y el resto exacto cuando se devuelve todo lo que queda.
     *
     * @param  list<array{linea: int, cantidad: string|int|float}>  $pedidas
     * @return list<array{item: VentaItem, cantidad: string, monto: int}>
     *
     * @throws ValidationException
     */
    private function lineas(Venta $venta, array $pedidas): array
    {
        $items = $venta->items()->get()->keyBy('orden');
        $devueltos = DevolucionItem::query()->join('devoluciones', 'devoluciones.id', '=', 'devolucion_items.devolucion_id')
            ->where('devoluciones.venta_id', $venta->id)->groupBy('venta_item_id')
            ->selectRaw('venta_item_id, SUM(devolucion_items.cantidad) as cantidad, SUM(devolucion_items.monto) as monto')->get()->keyBy('venta_item_id');
        $lineas = [];

        foreach ($pedidas as $indice => $pedida) {
            /** @var VentaItem|null $item */
            $item = $items->get($pedida['linea']);

            if ($item === null || isset($lineas[$item->id])) {
                throw ValidationException::withMessages(["items.$indice.linea" => 'Esta línea no es parte de la venta o está repetida.']);
            }

            $cantidad = bcadd((string) $pedida['cantidad'], '0', self::ESCALA);
            $yaDevuelta = (string) ($devueltos->get($item->id)?->cantidad ?? '0');
            $montoDevuelto = (int) ($devueltos->get($item->id)?->monto ?? 0);
            $disponible = bcsub($item->cantidad, $yaDevuelta, self::ESCALA);

            if ($item->unidad_venta === 'unidad' && bccomp($cantidad, bcadd($cantidad, '0', 0), self::ESCALA) !== 0) {
                throw ValidationException::withMessages(["items.$indice.cantidad" => 'Este producto se vende por unidad: usa una cantidad entera.']);
            }

            if (bccomp($cantidad, '0', self::ESCALA) <= 0 || bccomp($cantidad, $disponible, self::ESCALA) > 0) {
                throw ValidationException::withMessages(["items.$indice.cantidad" => 'Puedes devolver hasta '.$this->formatear($disponible).' de '.$item->nombre.'.']);
            }

            $restante = $item->montoFinal() - $montoDevuelto;
            $monto = bccomp($cantidad, $disponible, self::ESCALA) === 0
                ? $restante
                : min($restante, (int) round((float) bcdiv(bcmul((string) $item->montoFinal(), $cantidad, self::ESCALA), $item->cantidad, self::ESCALA)));

            $lineas[$item->id] = ['item' => $item, 'cantidad' => $cantidad, 'monto' => max(0, $monto)];
        }

        return array_values($lineas);
    }

    /**
     * El redondeo y el saldo pueden cambiar el importe final. Las líneas deben sumar exactamente
     * el dinero devuelto para que los reintentos y las devoluciones posteriores usen el monto real.
     *
     * @param  list<array{item: VentaItem, cantidad: string, monto: int}>  $lineas
     */
    private function repartirMonto(array &$lineas, int $monto): void
    {
        $ajuste = $monto - array_sum(array_column($lineas, 'monto'));

        for ($indice = count($lineas) - 1; $indice >= 0 && $ajuste < 0; $indice--) {
            $descuento = min($lineas[$indice]['monto'], -$ajuste);
            $lineas[$indice]['monto'] -= $descuento;
            $ajuste += $descuento;
        }

        if ($ajuste > 0) {
            $lineas[array_key_last($lineas)]['monto'] += $ajuste;
        }
    }

    /**
     * Con el adicional Inventario activo, devuelve al stock lo devuelto de los productos cuya venta
     * descontó stock, en orden fijo de producto para no cruzar bloqueos.
     *
     * @param  list<array{item: VentaItem, cantidad: string, monto: int}>  $lineas
     */
    private function reintegrarStock(Venta $venta, User $usuario, array $lineas): void
    {
        $negocio = Negocio::findOrFail($venta->negocio_id);

        if (! in_array(ConceptoTarifa::Inventario, $this->estadoPlan->adicionalesActivos($negocio), true)) {
            return;
        }

        $descontados = MovimientoStock::where('venta_id', $venta->id)->where('tipo', 'venta')->pluck('producto_id')->all();
        $reintegros = [];

        foreach ($lineas as $linea) {
            $productoId = $linea['item']->producto_id;

            if (in_array($productoId, $descontados, true)) {
                $reintegros[$productoId] = bcadd($reintegros[$productoId] ?? '0', $linea['cantidad'], self::ESCALA);
            }
        }

        ksort($reintegros);

        foreach ($reintegros as $productoId => $cantidad) {
            $producto = Producto::withoutGlobalScopes()->findOrFail($productoId);
            $this->stock->reintegrarPorVenta($producto, $venta->sucursal, $usuario, $cantidad, $venta, 'devolucion');
        }
    }

    private function formatear(string $cantidad): string
    {
        return rtrim(rtrim($cantidad, '0'), '.') ?: '0';
    }
}
