<?php

namespace App\Enums;

/**
 * Funcionalidades que cada negocio puede activar o desactivar. Un módulo
 * apagado se oculta del menú y de los formularios, pero no borra datos.
 */
enum Modulo: string
{
    case VentaPorPeso = 'venta_por_peso';
    case Modificadores = 'modificadores';
    case Comanda = 'comanda';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VentaPorPeso => 'Venta por medida',
            self::Modificadores => 'Modificadores',
            self::Comanda => 'Comanda y cocina',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::VentaPorPeso => 'Productos por kilo, litro o metro, como queso, jamón, alimento a granel o cable.',
            self::Modificadores => 'Opciones al vender: tipo de pan, agregados, salsas…',
            self::Comanda => 'Comanda para la cocina. La pantalla KDS se contrata como adicional.',
        };
    }
}
