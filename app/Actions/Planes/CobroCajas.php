<?php

namespace App\Actions\Planes;

use App\Enums\ConceptoTarifa;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\Tarifa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CobroCajas
{
    /**
     * @return array{requiere_aceptacion: bool, precio_caja_uf: string|null, total_mensual_uf: string|null,
     *     este_mes: array{dias: int, monto_uf: string|null}, cotizacion: string}
     */
    public function resumen(Negocio $negocio, Sucursal $sucursal, ?Caja $caja = null, bool $bloquear = false): array
    {
        $consulta = Tarifa::where('plan', $negocio->plan)->where('concepto', ConceptoTarifa::Caja);
        $tarifa = ($bloquear ? $consulta->lockForUpdate() : $consulta)->first();
        $activas = $sucursal->cajas()->withoutGlobalScopes()->where('activa', true)->orderBy('id')->get(['id', 'es_incluida']);
        $generaCobro = ($caja === null || ! $caja->activa) && $activas->isNotEmpty();
        $precio = $tarifa?->precio_uf;
        $total = $generaCobro ? ($precio !== null && (float) $precio >= 0 ? $precio : null) : '0.0000';
        $hoy = CarbonImmutable::now(config('facturacion.timezone'));
        $dias = $hoy->daysInMonth - $hoy->day + 1;
        $condiciones = [$negocio->id, $negocio->plan->value, $sucursal->public_id, $sucursal->activa,
            $caja?->public_id, $caja?->activa, $activas->toArray(), $tarifa?->public_id, $precio, $hoy->toDateString()];

        return [
            'en_prueba' => $negocio->enPrueba(),
            'requiere_aceptacion' => $generaCobro && (! $negocio->enPrueba() || $negocio->suscripciones()->whereNull('hasta')->exists()), 'precio_caja_uf' => $precio, 'total_mensual_uf' => $total,
            'este_mes' => ['dias' => $dias, 'monto_uf' => $total !== null
                ? Tarifa::importe(intdiv(Tarifa::unidades($total) * $dias + intdiv($hoy->daysInMonth, 2), $hoy->daysInMonth)) : null],
            'cotizacion' => hash_hmac('sha256', json_encode($condiciones, JSON_THROW_ON_ERROR), config('app.key')),
        ];
    }

    public function exigirAceptacion(Negocio $negocio, Sucursal $sucursal, ?Caja $caja, bool $aceptado, ?string $cotizacion): void
    {
        if (! $sucursal->activa) {
            throw ValidationException::withMessages(['sucursal' => 'Activa la sucursal antes de crear o activar sus cajas.']);
        }
        $resumen = $this->resumen($negocio, $sucursal, $caja, bloquear: true);
        if (! $resumen['requiere_aceptacion']) {
            return;
        }
        if ($resumen['total_mensual_uf'] === null) {
            throw ValidationException::withMessages(['tarifa' => 'La tarifa no está configurada. Contacta a soporte antes de contratar.']);
        }
        if (! $aceptado) {
            throw ValidationException::withMessages(['acepto_cobro' => 'Acepta el cobro mensual adicional para continuar.']);
        }
        if ($cotizacion === null || ! hash_equals($resumen['cotizacion'], $cotizacion)) {
            throw ValidationException::withMessages(['cotizacion' => 'Las condiciones han cambiado. Vuelve a revisar el cobro antes de aceptar.']);
        }
    }

    /** Se ejecuta dentro de la transacción que bloquea el negocio. */
    public function contratar(Negocio $negocio, Sucursal $sucursal, Caja $caja, User $usuario): void
    {
        $incluida = $this->asegurarIncluida($sucursal);
        if ($negocio->enPrueba() && ! $negocio->suscripciones()->whereNull('hasta')->exists()) {
            return;
        }
        if ($incluida?->id === $caja->id || ! $caja->activa || ! $sucursal->activa) {
            return;
        }
        if ($caja->contratacionVigente()->withoutGlobalScopes()->exists()) {
            return;
        }
        $tarifa = Tarifa::where('plan', $negocio->plan)->where('concepto', ConceptoTarifa::Caja)->firstOrFail();
        $periodo = new CajaAdicional;
        $periodo->forceFill([
            'negocio_id' => $negocio->id, 'caja_id' => $caja->id, 'tarifa_id' => $tarifa->id,
            'desde' => $negocio->enPrueba() ? $negocio->prueba_hasta : now(), 'aceptado_por_id' => $usuario->id, 'aceptado_at' => now(),
        ])->save();
    }

    public function cerrar(Caja $caja): void
    {
        $caja->contratacionVigente()->withoutGlobalScopes()->get()->each(fn ($contrato) => $contrato->update(['hasta' => $contrato->desde->max(now())]));
    }

    /** Conserva la incluida activa y, cuando falta, libera la primera activa disponible. */
    public function asegurarIncluida(Sucursal $sucursal): ?Caja
    {
        $incluida = $sucursal->cajas()->withoutGlobalScopes()->where('activa', true)->where('es_incluida', true)->first();
        if ($incluida !== null) {
            return $incluida;
        }
        $incluida = $sucursal->cajas()->withoutGlobalScopes()->where('activa', true)->orderBy('id')->first();
        if ($incluida !== null) {
            $incluida->forceFill(['es_incluida' => true])->save();
            $this->cerrar($incluida);
        }

        return $incluida;
    }
}
