<?php

namespace App\Http\Resources;

use App\Enums\Modulo;
use App\Models\Negocio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configuración del negocio para Administración → Mi negocio: datos, plan con
 * sus módulos y preferencias de operación.
 *
 * @mixin Negocio
 */
class NegocioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->only('codigo', 'nombre', 'rut', 'razon_social', 'giro', 'direccion', 'telefono', 'email'),
            'plan' => [
                'value' => $this->plan->value,
                'label' => $this->plan->etiqueta(),
                'descripcion' => $this->plan->descripcion(),
            ],
            'modulos' => array_map(fn (Modulo $modulo) => [
                'value' => $modulo->value,
                'label' => $modulo->etiqueta(),
                'descripcion' => $modulo->descripcion(),
                'incluido' => $this->plan->incluye($modulo),
                'activo' => $this->tieneModulo($modulo),
            ], Modulo::cases()),
            'cierre_ciego' => $this->cierre_ciego,
            'descuento_maximo_cajero' => $this->descuento_maximo_cajero,
        ];
    }
}
