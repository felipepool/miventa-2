<?php

namespace App\Support\Ventas;

/**
 * Montos de descuento en pesos enteros. Usa solo aritmética entera para que la pantalla
 * y el servidor coincidan al peso.
 */
class Descuentos
{
    public const string PORCENTAJE = 'porcentaje';

    public const string MONTO = 'monto';

    /**
     * Pesos que descuenta un porcentaje o un monto sobre una base. El porcentaje
     * se redondea al peso más cercano (la mitad hacia arriba).
     */
    public static function monto(int $base, string $tipo, int $valor): int
    {
        return $tipo === self::PORCENTAJE ? intdiv($base * $valor + 50, 100) : $valor;
    }

    /**
     * Reparte un descuento entre las líneas en proporción a su valor. Los pesos que
     * sobran del redondeo van a las líneas con el mayor resto (en empate, la primera),
     * así la suma es exacta.
     *
     * @param  list<int>  $bases
     * @return list<int>
     */
    public static function repartir(int $descuento, array $bases): array
    {
        $total = array_sum($bases);

        if ($descuento <= 0 || $total <= 0) {
            return array_fill(0, count($bases), 0);
        }

        $partes = [];
        $restos = [];

        foreach ($bases as $indice => $base) {
            $partes[$indice] = intdiv($descuento * $base, $total);
            $restos[$indice] = ($descuento * $base) % $total;
        }

        $orden = array_keys($restos);
        usort($orden, fn (int $a, int $b) => [$restos[$b], $a] <=> [$restos[$a], $b]);

        foreach (array_slice($orden, 0, $descuento - array_sum($partes)) as $indice) {
            $partes[$indice]++;
        }

        return array_values($partes);
    }
}
