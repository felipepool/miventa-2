<?php

namespace App\Actions\Planes;

use App\Enums\ConceptoTarifa;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\SucursalAdicional;
use App\Models\Tarifa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CobroSucursales
{
    public const int INCLUIDAS = 1;

    /**
     * @return array{incluidas: int, cajas_incluidas_por_sucursal: int, activas: int, adicionales: int,
     *     genera_cobro: bool, cajas_adicionales: int, requiere_aceptacion: bool,
     *     precio_sucursal_uf: string|null, precio_caja_uf: string|null, total_mensual_uf: string|null,
     *     este_mes: array{dias: int, dias_mes: int, monto_uf: string|null}, cotizacion: string}
     */
    public function resumen(Negocio $negocio, ?Sucursal $sucursal = null, bool $bloquear = false): array
    {
        $consulta = Tarifa::where('plan', $negocio->plan)->orderBy('id');
        $tarifas = ($bloquear ? $consulta->lockForUpdate() : $consulta)->get();
        $tarifaSucursal = $tarifas->firstWhere('concepto', ConceptoTarifa::Sucursal);
        $tarifaCaja = $tarifas->firstWhere('concepto', ConceptoTarifa::Caja);
        $activas = $negocio->sucursales()->withoutGlobalScopes()->where('activa', true)->count();
        $nuevaContratacion = $sucursal === null || ! $sucursal->activa;
        $generaCobro = $nuevaContratacion && $activas >= self::INCLUIDAS;
        $cajasAdicionales = $nuevaContratacion && $sucursal !== null ? max(0, $sucursal->cajas()->where('activa', true)->count() - 1) : 0;
        $precioSucursal = $tarifaSucursal?->precio_uf;
        $precioCaja = $tarifaCaja?->precio_uf;
        $configurada = (! $generaCobro || ($precioSucursal !== null && (float) $precioSucursal >= 0))
            && ($cajasAdicionales === 0 || ($precioCaja !== null && (float) $precioCaja >= 0));
        $total = $configurada ? ($generaCobro ? Tarifa::unidades($precioSucursal) : 0) + $cajasAdicionales * Tarifa::unidades($precioCaja ?? '0') : null;
        $hoy = CarbonImmutable::now(config('facturacion.timezone'));
        $dias = $hoy->daysInMonth - $hoy->day + 1;
        $condiciones = [$negocio->id, $negocio->plan->value, $sucursal?->public_id, $generaCobro, $cajasAdicionales,
            $tarifaSucursal?->public_id, $precioSucursal, $tarifaCaja?->public_id, $precioCaja, $hoy->toDateString()];

        return [
            'en_prueba' => $negocio->enPrueba(),
            'incluidas' => self::INCLUIDAS, 'cajas_incluidas_por_sucursal' => 1, 'activas' => $activas,
            'adicionales' => max(0, $activas - self::INCLUIDAS), 'genera_cobro' => $generaCobro,
            'cajas_adicionales' => $cajasAdicionales, 'requiere_aceptacion' => ($generaCobro || $cajasAdicionales > 0) && (! $negocio->enPrueba() || $negocio->suscripciones()->whereNull('hasta')->exists()),
            'precio_sucursal_uf' => $precioSucursal, 'precio_caja_uf' => $precioCaja,
            'total_mensual_uf' => $total !== null ? Tarifa::importe($total) : null,
            'este_mes' => ['dias' => $dias, 'dias_mes' => $hoy->daysInMonth,
                'monto_uf' => $total !== null ? Tarifa::importe(intdiv($total * $dias + intdiv($hoy->daysInMonth, 2), $hoy->daysInMonth)) : null],
            'cotizacion' => hash_hmac('sha256', json_encode($condiciones, JSON_THROW_ON_ERROR), config('app.key')),
        ];
    }

    public function exigirAceptacion(Negocio $negocio, ?Sucursal $sucursal, bool $aceptado, ?string $cotizacion): void
    {
        $resumen = $this->resumen($negocio, $sucursal, bloquear: true);

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

    public function contratar(Negocio $negocio, Sucursal $sucursal, User $usuario): void
    {
        if ($negocio->enPrueba() && ! $negocio->suscripciones()->whereNull('hasta')->exists()) {
            return;
        }
        $otrasActivas = $negocio->sucursales()->withoutGlobalScopes()->where('activa', true)->whereKeyNot($sucursal->id)->count();
        $tarifas = Tarifa::where('plan', $negocio->plan)->get();

        if ($otrasActivas >= self::INCLUIDAS) {
            $periodo = new SucursalAdicional;
            $periodo->forceFill([
                'negocio_id' => $negocio->id, 'sucursal_id' => $sucursal->id,
                'tarifa_id' => $tarifas->firstWhere('concepto', ConceptoTarifa::Sucursal)->id,
                'desde' => $negocio->enPrueba() ? $negocio->prueba_hasta : now(), 'aceptado_por_id' => $usuario->id, 'aceptado_at' => now(),
            ])->save();
        }

        foreach ($sucursal->cajas()->where('activa', true)->orderBy('id')->get() as $caja) {
            app(CobroCajas::class)->contratar($negocio, $sucursal, $caja, $usuario);
        }
    }

    public function cerrar(Sucursal $sucursal): void
    {
        SucursalAdicional::where('negocio_id', $sucursal->negocio_id)->where('sucursal_id', $sucursal->id)->whereNull('hasta')->get()->each(fn ($contrato) => $contrato->update(['hasta' => $contrato->desde->max(now())]));
        CajaAdicional::where('negocio_id', $sucursal->negocio_id)->whereHas('caja', fn ($query) => $query->where('sucursal_id', $sucursal->id))->whereNull('hasta')->get()->each(fn ($contrato) => $contrato->update(['hasta' => $contrato->desde->max(now())]));
    }
}
