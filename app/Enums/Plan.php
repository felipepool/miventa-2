<?php

namespace App\Enums;

/**
 * Plan contratado por el negocio. Define qué módulos tiene disponibles; dentro
 * de ellos, el administrador decide cuáles usa. El negocio no puede cambiarlo:
 * se asigna al contratar (por ahora, con `php artisan negocio:plan`).
 */
enum Plan: string
{
    case Retail = 'retail';
    case Comida = 'comida';
    case Mixto = 'mixto';

    /**
     * Mixto se conserva únicamente para los negocios que ya lo tienen.
     *
     * @return list<self>
     */
    public static function disponibles(?self $planActual = null): array
    {
        return $planActual === self::Mixto
            ? [self::Retail, self::Comida, self::Mixto]
            : [self::Retail, self::Comida];
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Retail => 'Comercio',
            self::Comida => 'Gastronomía',
            self::Mixto => 'Mixto',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Retail => 'Para almacenes, minimarkets, botillerías, ferreterías y tiendas que venden productos por unidad, peso o medida.',
            self::Comida => 'Para restaurantes, cafeterías, sangucherías y otros negocios de comida.',
            self::Mixto => 'Para negocios que venden productos y además preparan comida.',
        };
    }

    /**
     * Funcionalidades del plan, en lenguaje simple para el cliente.
     *
     * @return list<string>
     */
    public function funcionalidades(): array
    {
        $comunes = [
            'Punto de venta con lector de códigos y cámara del celular.',
            'Productos, categorías y marcas.',
            'Caja: apertura, movimientos, cierre y turnos.',
            'Historial de ventas, anulaciones y reportes.',
            'Usuarios con roles y permisos.',
        ];

        return [
            ...$comunes,
            ...array_map(fn (Modulo $modulo) => $modulo->etiqueta().': '.mb_lcfirst($modulo->descripcion()), $this->modulos()),
        ];
    }

    /**
     * Módulos que incluye el plan (y que se activan al contratarlo).
     *
     * @return list<Modulo>
     */
    public function modulos(): array
    {
        return match ($this) {
            self::Retail => [Modulo::VentaPorPeso],
            self::Comida => [Modulo::Modificadores, Modulo::Comanda],
            self::Mixto => Modulo::cases(),
        };
    }

    public function incluye(Modulo $modulo): bool
    {
        return in_array($modulo, $this->modulos(), true);
    }
}
