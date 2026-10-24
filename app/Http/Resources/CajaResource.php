<?php

namespace App\Http\Resources;

use App\Models\Caja;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Caja */
class CajaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->datosPublicos('nombre', 'activa', 'es_incluida'),
            'sucursal' => $this->sucursal->datosPublicos('nombre', 'activa', 'es_principal'),
            'precio_contratado_uf' => $this->contratacionVigente?->precio_uf,
        ];
    }
}
