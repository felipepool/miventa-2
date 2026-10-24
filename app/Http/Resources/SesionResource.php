<?php

namespace App\Http\Resources;

use App\Enums\Modulo;
use App\Enums\Permiso;
use App\Enums\Rol;
use App\Http\Middleware\EstablecerContexto;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Contexto\NegocioActual;
use App\Support\Contexto\SucursalActual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sesión del usuario autenticado: sus datos, roles, permisos y el contexto
 * (negocio, módulos y sucursales) en el que trabaja. Solo expone datos de su
 * propio negocio.
 *
 * @mixin User
 */
class SesionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $negocio = app(NegocioActual::class)->obtener();
        $sucursales = $request->attributes->get(EstablecerContexto::SUCURSALES_ACCESIBLES) ?? $this->resource->sucursalesAccesibles();

        return [
            'user' => new UserResource($this->resource),
            'roles' => array_map(fn (Rol $rol) => $rol->value, $this->resource->rolesAsignados()),
            'permisos' => array_map(fn (Permiso $permiso) => $permiso->value, $this->resource->permisos()),
            'contexto' => [
                'negocio' => $negocio?->only('codigo', 'nombre'),
                'modulos' => array_map(fn (Modulo $modulo) => $modulo->value, $negocio?->modulosActivos() ?? []),
                'sucursal' => app(SucursalActual::class)->obtener()?->datosPublicos('nombre'),
                'sucursales' => $sucursales->map(fn (Sucursal $sucursal) => $sucursal->datosPublicos('nombre', 'direccion', 'es_principal'))->values()->all(),
            ],
        ];
    }
}
