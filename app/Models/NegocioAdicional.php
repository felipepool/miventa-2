<?php

namespace App\Models;

use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;

#[Table('negocio_adicionales')]
#[Fillable(['hasta'])]
class NegocioAdicional extends Model
{
    use PerteneceANegocio;

    protected static function booted(): void
    {
        static::creating(function (NegocioAdicional $contrato): void {
            $tarifa = Tarifa::findOrFail($contrato->tarifa_id);
            if (! in_array($tarifa->concepto, ConceptoTarifa::adicionales(), true) || $tarifa->precio_uf === null || (float) $tarifa->precio_uf < 0) {
                throw ValidationException::withMessages(['tarifa' => 'La tarifa del adicional no está configurada.']);
            }
            $contrato->forceFill(['concepto' => $tarifa->concepto, 'plan' => $tarifa->plan, 'precio_uf' => $tarifa->precio_uf]);
        });
        static::updating(function (NegocioAdicional $contrato): void {
            if ($contrato->isDirty(['concepto', 'plan', 'precio_uf', 'tarifa_id', 'negocio_id', 'desde', 'aceptado_por_id', 'aceptado_at'])) {
                throw new LogicException('Las condiciones contratadas son inmutables.');
            }
        });
    }

    protected function casts(): array
    {
        return ['concepto' => ConceptoTarifa::class, 'plan' => Plan::class, 'precio_uf' => 'decimal:4',
            'desde' => 'immutable_datetime', 'hasta' => 'immutable_datetime', 'aceptado_at' => 'immutable_datetime'];
    }
}
