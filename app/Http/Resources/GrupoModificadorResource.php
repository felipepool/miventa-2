<?php

namespace App\Http\Resources;

use App\Models\GrupoModificador;
use App\Models\Modificador;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GrupoModificador */
class GrupoModificadorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->datosPublicos('nombre', 'referencia', 'tipo_seleccion', 'minimo', 'maximo', 'cantidad_gratis', 'precio_extra', 'activo'),
            'productos_count' => $this->whenCounted('productos'),
            'modificadores' => $this->whenLoaded('modificadores', fn () => $this->modificadores
                ->map(fn (Modificador $modificador) => $modificador->datosPublicos('nombre', 'precio_adicional', 'cantidad_maxima', 'orden', 'activo'))->all()),
        ];
    }
}
