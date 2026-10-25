<?php

namespace App\Enums;

/**
 * Entrada o salida de efectivo de una caja que no corresponde a una venta.
 */
enum TipoMovimientoCaja: string
{
    /** Ej. «dejaron $10.000 de sencillo». */
    case Ingreso = 'ingreso';

    /** Ej. «pago al proveedor de pan». */
    case Retiro = 'retiro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Retiro => 'Retiro',
        };
    }
}
