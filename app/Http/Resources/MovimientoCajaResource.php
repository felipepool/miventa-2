<?php

namespace App\Http\Resources;

use App\Models\MovimientoCaja;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MovimientoCaja */
class MovimientoCajaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->datosPublicos('monto', 'motivo'),
            'tipo' => $this->tipo->value,
            'usuario' => $this->usuario?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
