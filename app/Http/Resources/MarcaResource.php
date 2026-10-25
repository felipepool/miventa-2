<?php

namespace App\Http\Resources;

use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Marca */
class MarcaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->datosPublicos('nombre', 'activa'),
            'productos_count' => $this->whenCounted('productos'),
        ];
    }
}
