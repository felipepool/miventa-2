<?php

namespace App\Support\Ventas;

use App\Enums\MedioPago;
use App\Models\GrupoModificador;
use App\Models\Modificador;
use App\Models\Producto;
use App\Support\Modificadores\CalculadoraModificadores;
use App\Support\Modificadores\SeleccionModificadoresInvalida;
use Illuminate\Validation\ValidationException;

/**
 * Cálculo de una venta en pesos enteros con IVA incluido: líneas con sus modificadores,
 * descuentos y cobro por medio de pago. Es la fuente de verdad del servidor.
 */
class CalculadoraVenta
{
    public function __construct(private CalculadoraModificadores $modificadores) {}

    /**
     * Calcula cada línea: precio, modificadores del producto y descuento de la línea.
     *
     * @param  list<array{producto: Producto, cantidad: string, modificadores: list<array{id: string, cantidad: int}>, descuento: array{tipo: string, valor: int}|null, notas: string|null}>  $entradas
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function lineas(array $entradas, bool $usaModificadores): array
    {
        $lineas = [];

        foreach ($entradas as $indice => $entrada) {
            $producto = $entrada['producto'];
            $cobro = $this->cobrarModificadores($producto, $entrada['modificadores'], $usaModificadores, $indice);
            $unitario = $producto->precio + $cobro['total'];
            $cantidad = bcadd($entrada['cantidad'], '0', 3);
            $subtotal = (int) bcadd(bcmul($cantidad, (string) $unitario, 3), '0.5', 0);
            $descuento = $entrada['descuento'] === null ? 0 : $this->descuento($subtotal, $entrada['descuento'], "items.$indice.descuento");

            $lineas[] = [
                'producto' => $producto,
                'cantidad' => $cantidad,
                'precio_unitario' => $producto->precio,
                'modificadores_unitario' => $cobro['total'],
                'modificadores' => $cobro['lineas'],
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'descuento_venta' => 0,
                'notas' => $entrada['notas'],
            ];
        }

        return $lineas;
    }

    /**
     * Aplica el descuento de toda la venta, repartido entre las líneas según su valor.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @param  array{tipo: string, valor: int}|null  $descuento
     * @return array{lineas: list<array<string, mixed>>, subtotal: int, descuento: int, base: int}
     */
    public function descontarVenta(array $lineas, ?array $descuento): array
    {
        $bases = array_map(fn (array $linea) => $linea['subtotal'] - $linea['descuento'], $lineas);
        $descuentoVenta = $descuento === null ? 0 : $this->descuento(array_sum($bases), $descuento, 'descuento');

        foreach (Descuentos::repartir($descuentoVenta, $bases) as $indice => $parte) {
            $lineas[$indice]['descuento_venta'] = $parte;
        }

        $subtotal = array_sum(array_column($lineas, 'subtotal'));
        $descuentos = array_sum(array_column($lineas, 'descuento')) + $descuentoVenta;

        return ['lineas' => $lineas, 'subtotal' => $subtotal, 'descuento' => $descuentos, 'base' => $subtotal - $descuentos];
    }

    /**
     * Reparte el total entre los medios de pago. Los medios que no son efectivo traen su
     * monto; el efectivo cubre el resto, redondeado a $10, y admite vuelto.
     *
     * @param  list<array{medio: string, monto?: int|null, recibido?: int|null}>  $pagos
     * @return array{total: int, redondeo: int, pagos: list<array{medio: MedioPago, monto: int, recibido: int|null, vuelto: int}>}
     *
     * @throws ValidationException
     */
    public function cobro(int $base, array $pagos): array
    {
        $efectivo = null;
        $otros = [];

        foreach ($pagos as $pago) {
            $medio = MedioPago::from($pago['medio']);

            if (isset($otros[$medio->value]) || ($medio === MedioPago::Efectivo && $efectivo !== null)) {
                throw ValidationException::withMessages(['pagos' => 'Cada medio de pago puede usarse una sola vez.']);
            }

            if ($medio === MedioPago::Efectivo) {
                $efectivo = $pago;

                continue;
            }

            $monto = (int) ($pago['monto'] ?? 0);

            if ($monto < 1) {
                throw ValidationException::withMessages(['pagos' => 'Ingresa el monto de cada medio de pago.']);
            }

            $otros[$medio->value] = ['medio' => $medio, 'monto' => $monto, 'recibido' => null, 'vuelto' => 0];
        }

        $sinEfectivo = array_sum(array_column($otros, 'monto'));

        if ($sinEfectivo > $base) {
            throw ValidationException::withMessages(['pagos' => 'Los pagos superan el total de la venta.']);
        }

        if ($efectivo === null) {
            if ($sinEfectivo !== $base) {
                throw ValidationException::withMessages(['pagos' => 'Los pagos no cubren el total de la venta.']);
            }

            return ['total' => $base, 'redondeo' => 0, 'pagos' => array_values($otros)];
        }

        $porCobrar = $base - $sinEfectivo;

        if ($porCobrar === 0) {
            throw ValidationException::withMessages(['pagos' => 'El total ya está cubierto: quita el pago en efectivo.']);
        }

        $enEfectivo = Redondeo::efectivo($porCobrar);
        $recibido = (int) ($efectivo['recibido'] ?? $enEfectivo);

        if ($recibido < $enEfectivo) {
            throw ValidationException::withMessages(['pagos' => 'El efectivo recibido no alcanza para cubrir la venta.']);
        }

        $pagosFinales = array_values($otros);

        if ($enEfectivo > 0) {
            $pagosFinales[] = ['medio' => MedioPago::Efectivo, 'monto' => $enEfectivo, 'recibido' => $recibido, 'vuelto' => $recibido - $enEfectivo];
        }

        return ['total' => $sinEfectivo + $enEfectivo, 'redondeo' => $enEfectivo - $porCobrar, 'pagos' => $pagosFinales];
    }

    /**
     * @param  array{tipo: string, valor: int}  $descuento
     *
     * @throws ValidationException
     */
    private function descuento(int $base, array $descuento, string $campo): int
    {
        $monto = Descuentos::monto($base, $descuento['tipo'], $descuento['valor']);

        if ($descuento['tipo'] === Descuentos::PORCENTAJE && ($descuento['valor'] < 1 || $descuento['valor'] > 100)) {
            throw ValidationException::withMessages([$campo => 'El porcentaje de descuento debe estar entre 1 y 100.']);
        }

        if ($monto < 1 || $monto > $base) {
            throw ValidationException::withMessages([$campo => 'El descuento debe ser mayor que cero y no superar el valor de lo que descuenta.']);
        }

        return $monto;
    }

    /**
     * Valida los modificadores elegidos contra los grupos activos del producto.
     *
     * @param  list<array{id: string, cantidad: int}>  $elegidos
     * @return array{total: int, lineas: list<array{grupo: string, modificador: Modificador, cantidad: int, precio_adicional: int, total: int}>}
     *
     * @throws ValidationException
     */
    private function cobrarModificadores(Producto $producto, array $elegidos, bool $usaModificadores, int $indice): array
    {
        $campo = "items.$indice.modificadores";
        $grupos = $usaModificadores ? $producto->gruposModificadores->where('activo', true) : collect();
        $asignados = [];

        foreach ($grupos as $grupo) {
            foreach ($grupo->modificadores as $modificador) {
                $asignados[$modificador->public_id] = $grupo->id;
            }
        }

        $porGrupo = [];

        foreach ($elegidos as $elegido) {
            $grupoId = $asignados[$elegido['id']] ?? throw ValidationException::withMessages([$campo => 'Uno de los modificadores no está disponible para este producto.']);
            $porGrupo[$grupoId][] = ['modificador' => $elegido['id'], 'cantidad' => $elegido['cantidad']];
        }

        $total = 0;
        $lineas = [];

        /** @var GrupoModificador $grupo */
        foreach ($grupos as $grupo) {
            try {
                $calculo = $this->modificadores->calcular($grupo, $porGrupo[$grupo->id] ?? []);
            } catch (SeleccionModificadoresInvalida $excepcion) {
                throw ValidationException::withMessages([$campo => $excepcion->getMessage()]);
            }

            $total += $calculo['total'];

            foreach ($calculo['lineas'] as $linea) {
                $lineas[] = ['grupo' => $grupo->nombre, 'modificador' => $linea['modificador'], 'cantidad' => $linea['cantidad'],
                    'precio_adicional' => $linea['modificador']->precio_adicional, 'total' => $linea['total']];
            }
        }

        return ['total' => $total, 'lineas' => $lineas];
    }
}
