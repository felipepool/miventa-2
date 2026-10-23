<?php

namespace App\Models\Concerns;

use App\Models\Tarifa;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Copia el precio del catálogo al contratar; una edición posterior de tarifas nunca cambia el contrato. */
trait ConservaPrecioContratado
{
    protected static function bootConservaPrecioContratado(): void
    {
        static::creating(function (Model $contrato): void {
            $tarifa = Tarifa::findOrFail($contrato->tarifa_id);

            if ($tarifa->concepto !== static::CONCEPTO || $tarifa->precio_uf === null || (float) $tarifa->precio_uf < 0) {
                throw ValidationException::withMessages(['tarifa' => 'La tarifa de esta contratación no está configurada.']);
            }

            $contrato->forceFill(['plan' => $tarifa->plan, 'precio_uf' => $tarifa->precio_uf]);
        });

        static::updating(function (Model $contrato): void {
            if ($contrato->isDirty(['precio_uf', 'plan', 'tarifa_id', 'negocio_id', 'desde', 'aceptado_por_id', 'aceptado_at'])) {
                throw new LogicException('El precio y las condiciones contratadas son inmutables. Abre una nueva contratación.');
            }
        });
    }

    /** @return BelongsTo<Tarifa, $this> */
    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class);
    }

    /** @return array{dias: int, precio_uf: string, monto_uf: string} */
    public function importeDelMes(CarbonInterface $mes): array
    {
        $zona = config('facturacion.timezone');
        $inicioMes = CarbonImmutable::create($mes->year, $mes->month, 1, 0, 0, 0, $zona);
        $finMes = $inicioMes->addMonth();
        $desde = CarbonImmutable::instance($this->desde)->setTimezone($zona)->startOfDay()->max($inicioMes);
        $hasta = $this->hasta === null ? $finMes : CarbonImmutable::instance($this->hasta)->setTimezone($zona)->startOfDay()->min($finMes);
        $dias = max(0, (int) $desde->diffInDays($hasta, false));
        $diasMes = $inicioMes->daysInMonth;
        $unidades = intdiv(Tarifa::unidades($this->precio_uf) * $dias + intdiv($diasMes, 2), $diasMes);

        return ['dias' => $dias, 'precio_uf' => $this->precio_uf, 'monto_uf' => Tarifa::importe($unidades)];
    }
}
