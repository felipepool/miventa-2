<?php

namespace App\Enums;

enum Rol: string
{
    case Admin = 'admin';
    case Cajero = 'cajero';

    /** Solo ve la pantalla de cocina y cambia el estado de los pedidos. */
    case Cocina = 'cocina';
    case Garzon = 'garzon';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cajero => 'Cajero',
            self::Cocina => 'Cocina',
            self::Garzon => 'Garzón',
        };
    }

    /**
     * @return list<Permiso>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::Admin => Permiso::cases(),
            self::Cajero => [Permiso::OperarCaja, Permiso::Vender, Permiso::VerCocina, Permiso::AtenderMesas],
            self::Cocina => [Permiso::VerCocina],
            self::Garzon => [Permiso::AtenderMesas],
        };
    }
}
