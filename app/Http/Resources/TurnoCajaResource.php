<?php

namespace App\Http\Resources;

use App\Enums\TipoMovimientoCaja;
use App\Models\TurnoCaja;
use App\Support\Caja\Denominaciones;
use App\Support\Contexto\NegocioActual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Turno de caja. Con el cierre ciego activo, quien no tiene `gestionar_cajas`
 * no recibe el esperado ni la diferencia.
 *
 * @mixin TurnoCaja
 */
class TurnoCajaResource extends JsonResource
{
    public static function puedeVerEsperado(Request $request): bool
    {
        return ! app(NegocioActual::class)->obtener()->cierre_ciego || (bool) $request->user()?->can('gestionar_cajas');
    }

    /**
     * Billetes y monedas contados, de mayor a menor valor (la base de datos no conserva el orden).
     *
     * @return list<array{valor: int, cantidad: int}>|null
     */
    private function conteoOrdenado(): ?array
    {
        if ($this->conteo === null) {
            return null;
        }

        $conteo = [];

        foreach (Denominaciones::VALORES as $valor) {
            if (isset($this->conteo[$valor])) {
                $conteo[] = ['valor' => $valor, 'cantidad' => (int) $this->conteo[$valor]];
            }
        }

        return $conteo;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $datos = [
            ...$this->datosPublicos('numero', 'monto_inicial', 'nota_apertura', 'nota_cierre', 'monto_contado'),
            'caja' => $this->whenLoaded('caja', fn () => $this->caja->datosPublicos('nombre')),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario->name),
            'cerrado_por' => $this->whenLoaded('cerradoPor', fn () => $this->cerradoPor?->name),
            'abierta_at' => $this->abierta_at->toIso8601String(),
            'cerrada_at' => $this->cerrada_at?->toIso8601String(),
            'ingresos' => (int) ($this->ingresos ?? $this->sumarMovimientos(TipoMovimientoCaja::Ingreso)),
            'retiros' => (int) ($this->retiros ?? $this->sumarMovimientos(TipoMovimientoCaja::Retiro)),
            'ventas_efectivo' => (int) ($this->ventas_efectivo ?? $this->sumarVentasEfectivo()),
            'devoluciones_efectivo' => (int) ($this->devoluciones_efectivo ?? $this->sumarDevolucionesEfectivo()),
            'conteo' => $this->conteoOrdenado(),
            'movimientos' => $this->whenLoaded('movimientos', fn () => MovimientoCajaResource::collection($this->movimientos)->resolve($request)),
        ];

        if (self::puedeVerEsperado($request)) {
            $datos['monto_esperado'] = $this->estaAbierto() ? $this->calcularEsperado() : $this->monto_esperado;
            $datos['diferencia'] = $this->diferencia;
        }

        return $datos;
    }
}
