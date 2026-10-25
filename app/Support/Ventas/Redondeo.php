<?php

namespace App\Support\Ventas;

/**
 * Redondeo de montos pagados en efectivo (Ley 20.956): al múltiplo de $10 más
 * cercano; si termina en 1 a 5 se redondea hacia abajo y de 6 a 9 hacia arriba.
 * Los pagos con tarjeta o transferencia no se redondean.
 */
class Redondeo
{
    public static function efectivo(int $monto): int
    {
        $ultimoDigito = $monto % 10;

        return $ultimoDigito <= 5 ? $monto - $ultimoDigito : $monto + (10 - $ultimoDigito);
    }
}
