<?php

namespace App\Actions\Caja;

use App\Models\Caja;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Abre un turno en una caja de la sucursal. Una caja tiene a lo más un turno
 * abierto y un usuario solo puede tener uno abierto a la vez: se bloquean el
 * usuario y la caja para que dos aperturas simultáneas no lo violen.
 */
class AbrirTurno
{
    public function ejecutar(User $usuario, Sucursal $sucursal, string $cajaPublicId, int $montoInicial, ?string $nota = null): TurnoCaja
    {
        return DB::transaction(function () use ($usuario, $sucursal, $cajaPublicId, $montoInicial, $nota): TurnoCaja {
            User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            $caja = Caja::where('sucursal_id', $sucursal->id)->where('public_id', $cajaPublicId)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['caja' => 'La caja no pertenece a la sucursal actual.']);

            if (! $caja->activa) {
                throw ValidationException::withMessages(['caja' => 'Esta caja está desactivada.']);
            }

            if (TurnoCaja::abiertos()->where('caja_id', $caja->id)->exists()) {
                throw ValidationException::withMessages(['caja' => 'Esta caja ya está abierta por otra persona.']);
            }

            if (TurnoCaja::withoutGlobalScopes()->abiertos()->where('user_id', $usuario->id)->exists()) {
                throw ValidationException::withMessages(['caja' => 'Ya tienes una caja abierta. Ciérrala antes de abrir otra.']);
            }

            $turno = new TurnoCaja(['monto_inicial' => $montoInicial, 'nota_apertura' => $nota]);
            $turno->forceFill([
                'negocio_id' => $caja->negocio_id,
                'sucursal_id' => $caja->sucursal_id,
                'caja_id' => $caja->id,
                'user_id' => $usuario->id,
                'numero' => (int) TurnoCaja::withoutGlobalScopes()->where('caja_id', $caja->id)->max('numero') + 1,
                'abierta_at' => now(),
            ])->save();

            return $turno->refresh();
        }, 3);
    }
}
