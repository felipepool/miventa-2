<?php

namespace App\Enums;

enum EstadoVenta: string
{
    case Pagada = 'pagada';
    case Anulada = 'anulada';
}
