<?php

namespace App\Actions\Planes;

use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Negocio;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Contratación interna; la administración de planes se incorpora en su propio módulo. */
class ContratarPlan
{
    public function ejecutar(Negocio $negocio, Plan $plan, User $aceptadoPor, string $precioAceptado): Suscripcion
    {
        return DB::transaction(function () use ($negocio, $plan, $aceptadoPor, $precioAceptado): Suscripcion {
            $negocio = Negocio::whereKey($negocio->id)->lockForUpdate()->firstOrFail();
            abort_unless($aceptadoPor->negocio_id === $negocio->id, 403);
            $vigente = Suscripcion::where('negocio_id', $negocio->id)->whereNull('hasta')->first();
            if ($vigente?->plan === $plan) {
                return $vigente;
            }

            $tarifa = Tarifa::where('concepto', ConceptoTarifa::Plan)->where('plan', $plan)->lockForUpdate()->first();
            if ($tarifa?->precio_uf === null || ! is_numeric($precioAceptado)
                || $tarifa->precio_uf !== number_format((float) $precioAceptado, 4, '.', '')) {
                throw ValidationException::withMessages(['precio_uf' => 'Configura y confirma el precio vigente del plan antes de contratar.']);
            }

            $vigente?->update(['hasta' => now()]);
            $suscripcion = new Suscripcion;
            $suscripcion->forceFill([
                'negocio_id' => $negocio->id, 'tarifa_id' => $tarifa->id, 'desde' => now(),
                'aceptado_por_id' => $aceptadoPor->id, 'aceptado_at' => now(),
            ])->save();
            $negocio->update(['plan' => $plan, 'modulos' => $plan->modulos()]);

            return $suscripcion;
        }, 3);
    }
}
