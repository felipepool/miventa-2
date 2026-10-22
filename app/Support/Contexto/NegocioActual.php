<?php

namespace App\Support\Contexto;

use App\Models\Negocio;
use Illuminate\Container\Attributes\Scoped;

/**
 * Negocio en el que se está trabajando durante la petición actual.
 * Lo establece el middleware EstablecerContexto a partir del usuario autenticado.
 */
#[Scoped]
class NegocioActual
{
    private ?Negocio $negocio = null;

    public function establecer(?Negocio $negocio): void
    {
        $this->negocio = $negocio;
    }

    public function obtener(): ?Negocio
    {
        return $this->negocio;
    }

    public function id(): ?int
    {
        return $this->negocio?->id;
    }

    public function existe(): bool
    {
        return $this->negocio !== null;
    }
}
