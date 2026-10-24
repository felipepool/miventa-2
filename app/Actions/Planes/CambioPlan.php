<?php

namespace App\Actions\Planes;

use App\Enums\ConceptoTarifa;
use App\Enums\Modulo;
use App\Enums\Plan;
use App\Models\Negocio;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambio de plan desde Mi plan: cotiza el nuevo precio y sus módulos, y lo
 * contrata solo con la aceptación de esas mismas condiciones.
 */
class CambioPlan
{
    public function __construct(private ContratarPlan $contratar) {}

    /**
     * @return array{plan: string, precio_uf: string|null, modulos_agregados: list<array{value: string, label: string}>,
     *     modulos_retirados: list<array{value: string, label: string}>, sin_retorno: bool, cotizacion: string}
     */
    public function resumen(Negocio $negocio, Plan $plan, bool $bloquear = false): array
    {
        $consulta = Tarifa::where('concepto', ConceptoTarifa::Plan)->where('plan', $plan);
        $tarifa = ($bloquear ? $consulta->lockForUpdate() : $consulta)->first();
        $precio = $tarifa?->precio_uf !== null && (float) $tarifa->precio_uf >= 0 ? $tarifa->precio_uf : null;
        $vigente = Suscripcion::where('negocio_id', $negocio->id)->whereNull('hasta')->value('id');
        $etiquetas = fn (array $modulos): array => array_map(fn (Modulo $modulo) => ['value' => $modulo->value, 'label' => $modulo->etiqueta()], array_values($modulos));
        $condiciones = [$negocio->id, $negocio->plan->value, $vigente, $plan->value, $tarifa?->public_id, $precio];

        return [
            'plan' => $plan->value,
            'precio_uf' => $precio,
            'modulos_agregados' => $etiquetas(array_filter($plan->modulos(), fn (Modulo $modulo) => ! $negocio->plan->incluye($modulo))),
            'modulos_retirados' => $etiquetas(array_filter($negocio->plan->modulos(), fn (Modulo $modulo) => ! $plan->incluye($modulo))),
            'sin_retorno' => ! in_array($negocio->plan, Plan::disponibles(), true),
            'cotizacion' => hash_hmac('sha256', json_encode($condiciones, JSON_THROW_ON_ERROR), config('app.key')),
        ];
    }

    /**
     * Bloquea el negocio para que dos envíos simultáneos no abran contratos duplicados.
     */
    public function cambiar(Negocio $negocio, Plan $plan, User $usuario, bool $aceptado, ?string $cotizacion): Suscripcion
    {
        return DB::transaction(function () use ($negocio, $plan, $usuario, $aceptado, $cotizacion): Suscripcion {
            $negocio = Negocio::whereKey($negocio->id)->lockForUpdate()->firstOrFail();
            $usuario = $usuario->fresh();
            abort_unless($usuario->puedeIniciarSesion() && $usuario->negocio_id === $negocio->id && $usuario->can('gestionar_negocio'), 403);

            if ($negocio->plan === $plan || ! in_array($plan, Plan::disponibles($negocio->plan), true)) {
                throw ValidationException::withMessages(['plan' => 'Elige un plan distinto al que tienes contratado.']);
            }
            $resumen = $this->resumen($negocio, $plan, bloquear: true);
            if ($resumen['precio_uf'] === null) {
                throw ValidationException::withMessages(['tarifa' => 'El precio de este plan no está configurado. Contacta a soporte para cambiarte.']);
            }
            if (! $aceptado) {
                throw ValidationException::withMessages(['acepto_cobro' => 'Acepta el nuevo cobro mensual para cambiar de plan.']);
            }
            if ($cotizacion === null || ! hash_equals($resumen['cotizacion'], $cotizacion)) {
                throw ValidationException::withMessages(['cotizacion' => 'Las condiciones han cambiado. Vuelve a revisar el plan antes de aceptar.']);
            }

            return $this->contratar->ejecutar($negocio, $plan, $usuario, $resumen['precio_uf']);
        }, 3);
    }
}
