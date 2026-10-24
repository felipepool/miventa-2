<?php

namespace App\Enums;

enum ConceptoTarifa: string
{
    case Plan = 'plan';
    case Sucursal = 'sucursal';
    case Caja = 'caja';
    case Documentos = 'documentos';
    case Mesas = 'mesas';
    case Kds = 'kds';

    /** @return list<self> */
    public static function adicionales(): array
    {
        return [self::Documentos, self::Mesas, self::Kds];
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Plan => 'Plan', self::Sucursal => 'Sucursal adicional', self::Caja => 'Caja adicional',
            self::Documentos => 'Facturación electrónica', self::Mesas => 'Gestión de mesas', self::Kds => 'KDS',
        };
    }
}
