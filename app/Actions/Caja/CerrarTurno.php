<?php

namespace App\Actions\Caja;

use App\Models\TurnoCaja;
use App\Models\User;
use App\Support\Caja\Denominaciones;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierra un turno: calcula el esperado, registra lo contado y congela la
 * diferencia. El cierre es definitivo.
 */
class CerrarTurno
{
    /**
     * @param  array<int|string, int|string|null>|null  $conteo  billetes y monedas contados; si se omite se usa `$montoContado`
     */
    public function ejecutar(TurnoCaja $turno, User $cerradoPor, ?array $conteo, ?int $montoContado, ?string $nota = null): TurnoCaja
    {
        return DB::transaction(function () use ($turno, $cerradoPor, $conteo, $montoContado, $nota): TurnoCaja {
            $turno = TurnoCaja::whereKey($turno->id)->lockForUpdate()->firstOrFail();

            if (! $turno->estaAbierto()) {
                throw ValidationException::withMessages(['monto_contado' => 'Esta caja ya fue cerrada.']);
            }

            if ($turno->user_id !== $cerradoPor->id) {
                abort_unless($cerradoPor->can('gestionar_cajas'), 403);
            }

            $conteoLimpio = $conteo === null ? null : Denominaciones::normalizar($conteo);
            $contado = $conteoLimpio === null ? (int) $montoContado : Denominaciones::sumar($conteoLimpio);
            $esperado = $turno->calcularEsperado();

            $turno->forceFill([
                'cerrada_at' => now(),
                'cerrada_por_id' => $cerradoPor->id,
                'nota_cierre' => $nota,
                'monto_esperado' => $esperado,
                'monto_contado' => $contado,
                'diferencia' => $contado - $esperado,
                'conteo' => $conteoLimpio,
            ])->save();

            return $turno->refresh();
        }, 3);
    }
}
