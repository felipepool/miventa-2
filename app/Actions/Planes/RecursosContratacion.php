<?php

namespace App\Actions\Planes;

use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Caja;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\SucursalAdicional;
use App\Models\Tarifa;
use App\Models\User;

class RecursosContratacion
{
    /** @return list<array<string, mixed>> */
    public function resumen(Negocio $negocio, Plan $plan): array
    {
        $tarifas = Tarifa::where('plan', $plan)->get()->keyBy(fn ($tarifa) => $tarifa->concepto->value);
        $sucursales = $negocio->sucursales()->where('activa', true)->with(['cajas' => fn ($query) => $query->where('activa', true)->orderByDesc('es_incluida')->orderBy('id')])
            ->orderByDesc('es_principal')->orderBy('id')->get();
        $contratosSucursales = SucursalAdicional::where('negocio_id', $negocio->id)->whereNull('hasta')->get()->keyBy('sucursal_id');
        $contratosCajas = CajaAdicional::where('negocio_id', $negocio->id)->whereNull('hasta')->get()->keyBy('caja_id');
        $lineas = [];
        foreach ($sucursales as $indice => $sucursal) {
            if ($indice > 0) {
                $contrato = $contratosSucursales->get($sucursal->id);
                $tarifa = $tarifas->get(ConceptoTarifa::Sucursal->value);
                $lineas[] = ['value' => 'sucursal:'.$sucursal->public_id, 'sucursal' => $sucursal->public_id, 'tipo' => 'sucursal', 'id' => $sucursal->id, 'concepto' => 'Sucursal adicional', 'detalle' => $sucursal->nombre,
                    'contrato_id' => $contrato?->id, 'tarifa_id' => $tarifa?->id, 'precio_uf' => $contrato?->precio_uf ?? $tarifa?->precio_uf];
            }
            foreach ($sucursal->cajas->skip(1) as $caja) {
                $contrato = $contratosCajas->get($caja->id);
                $tarifa = $tarifas->get(ConceptoTarifa::Caja->value);
                $lineas[] = ['value' => 'caja:'.$caja->public_id, 'sucursal' => $sucursal->public_id, 'tipo' => 'caja', 'id' => $caja->id, 'concepto' => 'Caja adicional', 'detalle' => $caja->nombre.' · '.$sucursal->nombre,
                    'contrato_id' => $contrato?->id, 'tarifa_id' => $tarifa?->id, 'precio_uf' => $contrato?->precio_uf ?? $tarifa?->precio_uf];
            }
        }

        return $lineas;
    }

    /** Solo dentro de la contratación aceptada, bajo el bloqueo del negocio. */
    public function desactivar(Negocio $negocio, array $excluir): void
    {
        foreach ($excluir as $valor) {
            [$tipo, $id] = explode(':', $valor, 2);
            if ($tipo === 'sucursal') {
                $sucursal = $negocio->sucursales()->where('public_id', $id)->firstOrFail();
                $sucursal->update(['activa' => false]);
                app(CobroSucursales::class)->cerrar($sucursal);
            } else {
                $caja = Caja::where('negocio_id', $negocio->id)->where('public_id', $id)->firstOrFail();
                $caja->update(['activa' => false]);
                app(CobroCajas::class)->cerrar($caja);
            }
        }
    }

    public function contratar(Negocio $negocio, Plan $plan, User $usuario): void
    {
        foreach ($this->resumen($negocio, $plan) as $linea) {
            if ($linea['contrato_id'] !== null) {
                continue;
            }
            $contrato = $linea['tipo'] === 'sucursal' ? new SucursalAdicional : new CajaAdicional;
            $contrato->forceFill([
                'negocio_id' => $negocio->id, $linea['tipo'].'_id' => $linea['id'], 'tarifa_id' => $linea['tarifa_id'],
                'desde' => $negocio->enPrueba() ? $negocio->prueba_hasta : now(),
                'aceptado_por_id' => $usuario->id, 'aceptado_at' => now(),
            ])->save();
        }
    }
}
