<?php

namespace App\Actions\Planes;

use App\Enums\ConceptoTarifa;
use App\Models\Negocio;

class EstadoPlan
{
    /** @return array<string, mixed> */
    public function resumen(Negocio $negocio): array
    {
        $contrato = $negocio->suscripciones()->whereNull('hasta')->latest('desde')->first();
        $prueba = $negocio->enPrueba();
        $activos = $negocio->adicionales()->where('desde', '<=', now())
            ->where(fn ($query) => $query->whereNull('hasta')->orWhere('hasta', '>', now()))->pluck('concepto')->all();

        return [
            'en_prueba' => $prueba, 'prueba_hasta' => $negocio->prueba_hasta?->toIso8601String(),
            'dias_restantes' => $prueba ? (int) ceil(now()->diffInDays($negocio->prueba_hasta)) : 0,
            'solo_lectura' => $negocio->soloLectura(), 'contratado' => $contrato !== null,
            'cobro_desde' => $contrato?->desde->toIso8601String(),
            'adicionales_activos' => $prueba ? array_map(fn ($concepto) => $concepto->value, ConceptoTarifa::adicionales())
                : array_map(fn ($concepto) => $concepto->value, $activos),
            'facturacion' => ['ambiente' => 'certificacion', 'valor_tributario' => false,
                'aviso' => 'Documentos de prueba sin valor tributario. Motor-DTE en certificación.'],
        ];
    }
}
