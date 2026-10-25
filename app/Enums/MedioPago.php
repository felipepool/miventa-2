<?php

namespace App\Enums;

/**
 * Cómo paga el cliente. Solo el efectivo se redondea y admite vuelto.
 */
enum MedioPago: string
{
    case Efectivo = 'efectivo';
    case Debito = 'debito';
    case Credito = 'credito';
    case Transferencia = 'transferencia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Debito => 'Débito',
            self::Credito => 'Crédito',
            self::Transferencia => 'Transferencia',
        };
    }
}
