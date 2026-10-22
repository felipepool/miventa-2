<?php

namespace App\Http\Resources;

use App\Enums\Rol;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuario en el mantenedor de usuarios.
 *
 * @mixin User
 */
class UsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->datosPublicos('name', 'username', 'email', 'activo'),
            'roles' => array_map(fn (Rol $rol) => $rol->value, $this->rolesAsignados()),
            'sucursales' => $this->whenLoaded('sucursales', fn () => $this->sucursales
                ->map(fn (Sucursal $sucursal) => $sucursal->datosPublicos('nombre'))
                ->values()),
            'es_usuario_actual' => $this->resource->is($request->user()),
        ];
    }
}
