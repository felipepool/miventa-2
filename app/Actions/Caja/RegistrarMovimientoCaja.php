<?php

namespace App\Actions\Caja;

use App\Enums\TipoMovimientoCaja;
use App\Models\MovimientoCaja;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra un ingreso o retiro de efectivo en el turno abierto del usuario. El
 * turno se bloquea durante el registro para que dos retiros simultáneos no
 * superen el efectivo disponible.
 */
class RegistrarMovimientoCaja
{
    public function ejecutar(User $usuario, TipoMovimientoCaja $tipo, int $monto, string $motivo, bool $revelarSaldo): MovimientoCaja
    {
        return DB::transaction(function () use ($usuario, $tipo, $monto, $motivo, $revelarSaldo): MovimientoCaja {
            $turno = TurnoCaja::abiertos()->where('user_id', $usuario->id)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['monto' => 'No tienes una caja abierta.']);

            if ($tipo === TipoMovimientoCaja::Retiro && $monto > $turno->calcularEsperado()) {
                throw ValidationException::withMessages(['monto' => $revelarSaldo
                    ? 'No hay suficiente efectivo en la caja para este retiro. Disponible: $'.number_format($turno->calcularEsperado(), 0, ',', '.').'.'
                    : 'No hay suficiente efectivo en la caja para este retiro.']);
            }

            $movimiento = new MovimientoCaja;
            $movimiento->forceFill(['turno_caja_id' => $turno->id, 'user_id' => $usuario->id, 'tipo' => $tipo, 'monto' => $monto, 'motivo' => $motivo])->save();

            return $movimiento->refresh();
        }, 3);
    }
}
