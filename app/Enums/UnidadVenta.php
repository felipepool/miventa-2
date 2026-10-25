<?php

namespace App\Enums;

/**
 * Cómo se vende un producto: por unidad o por kilo (el precio es por kilo).
 */
enum UnidadVenta: string
{
    case Unidad = 'unidad';
    case Kilogramo = 'kg';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Unidad => 'Unidad',
            self::Kilogramo => 'Kilo',
        };
    }
}
