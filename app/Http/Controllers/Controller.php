<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Cantidades de registros por página que aceptan los listados con selector. */
    public const array POR_PAGINA = [5, 10, 25, 50, 100];
}
