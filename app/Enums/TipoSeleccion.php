<?php

namespace App\Enums;

/**
 * Cómo se eligen las opciones de un grupo de modificadores.
 */
enum TipoSeleccion: string
{
    /** Una sola opción (ej. tipo de pan). */
    case Unica = 'unica';

    /** Varias opciones, cada una sí o no (ej. agregados). */
    case Multiple = 'multiple';

    /** Varias unidades de cada opción (ej. salsas). */
    case Cantidad = 'cantidad';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Unica => 'Selección única',
            self::Multiple => 'Selección múltiple',
            self::Cantidad => 'Cantidad',
        };
    }
}
