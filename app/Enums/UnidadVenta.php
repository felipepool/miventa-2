<?php

namespace App\Enums;

/**
 * Unidad en que se mide un producto: por unidad, peso, volumen o largo
 * (el precio y el costo se expresan por esa unidad).
 */
enum UnidadVenta: string
{
    case Unidad = 'unidad';
    case Kilogramo = 'kg';
    case Gramo = 'g';
    case Litro = 'l';
    case Mililitro = 'ml';
    case Metro = 'm';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Unidad => 'Unidad',
            self::Kilogramo => 'Kilo',
            self::Gramo => 'Gramo',
            self::Litro => 'Litro',
            self::Mililitro => 'Mililitro',
            self::Metro => 'Metro',
        };
    }
}
