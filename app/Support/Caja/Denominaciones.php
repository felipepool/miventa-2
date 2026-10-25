<?php

namespace App\Support\Caja;

/**
 * Billetes y monedas chilenos que se cuentan al cerrar un turno.
 */
class Denominaciones
{
    /** @var list<int> */
    public const array VALORES = [20000, 10000, 5000, 2000, 1000, 500, 100, 50, 10];

    /**
     * Deja solo denominaciones válidas con cantidades enteras mayores que cero.
     *
     * @param  array<int|string, int|string|null>  $conteo
     * @return array<int, int>
     */
    public static function normalizar(array $conteo): array
    {
        $limpio = [];

        foreach (self::VALORES as $valor) {
            $cantidad = (int) ($conteo[$valor] ?? 0);

            if ($cantidad > 0) {
                $limpio[$valor] = $cantidad;
            }
        }

        return $limpio;
    }

    /**
     * @param  array<int, int>  $conteo
     */
    public static function sumar(array $conteo): int
    {
        return array_sum(array_map(fn (int $valor, int $cantidad) => $valor * $cantidad, array_keys($conteo), $conteo));
    }
}
