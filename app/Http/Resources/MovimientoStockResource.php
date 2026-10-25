<?php

namespace App\Http\Resources;

use App\Models\MovimientoStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MovimientoStock */
class MovimientoStockResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->datosPublicos('tipo', 'cantidad', 'stock_resultante', 'motivo'),
            'usuario' => $this->usuario?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
