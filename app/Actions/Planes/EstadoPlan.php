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

        return [
            'en_prueba' => $prueba, 'prueba_hasta' => $negocio->prueba_hasta?->toIso8601String(),
            'dias_restantes' => $prueba ? (int) ceil(now()->diffInDays($negocio->prueba_hasta)) : 0,
            'solo_lectura' => $negocio->soloLectura(), 'contratado' => $contrato !== null,
            'cobro_desde' => $contrato?->desde->toIso8601String(),
            'adicionales_activos' => array_map(fn (ConceptoTarifa $concepto) => $concepto->value, $this->adicionalesActivos($negocio)),
            'facturacion' => ['ambiente' => 'certificacion', 'valor_tributario' => false,
                'aviso' => 'Documentos de prueba sin valor tributario. Motor-DTE en certificación.'],
        ];
    }

    /**
     * Adicionales disponibles hoy: todos durante la prueba y, después, solo los contratados.
     *
     * @return list<ConceptoTarifa>
     */
    public function adicionalesActivos(Negocio $negocio): array
    {
        if ($negocio->enPrueba()) {
            return ConceptoTarifa::adicionales();
        }

        return $negocio->adicionales()->where('desde', '<=', now())
            ->where(fn ($query) => $query->whereNull('hasta')->orWhere('hasta', '>', now()))
            ->pluck('concepto')->all();
    }
}
