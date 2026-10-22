<?php

namespace App\Support\Contexto;

use App\Models\Sucursal;
use Illuminate\Container\Attributes\Scoped;

/**
 * Sucursal activa del usuario durante la petición actual.
 * Se guarda en la sesión para que cada dispositivo mantenga su propia sucursal.
 */
#[Scoped]
class SucursalActual
{
    public const string CLAVE_SESION = 'sucursal_actual_id';

    private ?Sucursal $sucursal = null;

    public function establecer(?Sucursal $sucursal): void
    {
        $this->sucursal = $sucursal;
    }

    public function obtener(): ?Sucursal
    {
        return $this->sucursal;
    }

    public function id(): ?int
    {
        return $this->sucursal?->id;
    }

    public function existe(): bool
    {
        return $this->sucursal !== null;
    }
}
