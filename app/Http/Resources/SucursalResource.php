<?php

namespace App\Http\Resources;

use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sucursal en el mantenedor de sucursales.
 *
 * @mixin Sucursal
 */
class SucursalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->datosPublicos('nombre', 'direccion', 'telefono', 'es_principal', 'activa'),
            'usuarios_count' => $this->whenCounted('usuarios'),
            'cajas_count' => $this->whenCounted('cajas'),
        ];
    }
}
