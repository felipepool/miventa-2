<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Planes\CambioPlan;
use App\Actions\Planes\CobroSucursales;
use App\Actions\Planes\EstadoPlan;
use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Models\CajaAdicional;
use App\Models\Negocio;
use App\Models\SucursalAdicional;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use App\Support\Contexto\NegocioActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Plan del negocio para su administrador: qué incluye, cuánto paga cada mes y
 * el cambio a otro plan.
 */
class MiPlanController extends Controller
{
    public function __construct(private NegocioActual $negocioActual) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->datos($this->negocioActual->obtener())]);
    }

    public function update(Request $request, CambioPlan $cambio): JsonResponse
    {
        $datos = $request->validate([
            'plan' => ['required', 'string', Rule::enum(Plan::class)],
            'acepto_cobro' => ['sometimes', 'boolean'],
            'cotizacion' => ['nullable', 'string', 'size:64'],
            'adicionales' => ['present', 'array'],
            'excluir' => ['sometimes', 'array'],
            'excluir.*' => ['string', 'distinct', 'regex:/^(sucursal|caja):[a-z0-9]{26}$/i'],
            'adicionales.*' => ['string', 'distinct', Rule::in(array_map(fn ($concepto) => $concepto->value, ConceptoTarifa::adicionales()))],
        ]);
        $cambio->cambiar($this->negocioActual->obtener(), Plan::from($datos['plan']), $request->user(), $request->boolean('acepto_cobro'), $datos['cotizacion'] ?? null, $datos['adicionales'], $datos['excluir'] ?? []);

        return response()->json(['data' => $this->datos($this->negocioActual->obtener()->refresh())]);
    }

    /**
     * Cobro mensual vigente con el precio contratado de cada concepto.
     *
     * @return array<string, mixed>
     */
    private function datos(Negocio $negocio): array
    {
        $suscripcion = Suscripcion::whereNull('hasta')->latest('desde')->first();
        $precioPlan = $suscripcion?->precio_uf;
        $sucursales = SucursalAdicional::with('sucursal')->whereNull('hasta')->orderBy('desde')->orderBy('id')->get();
        $cajas = CajaAdicional::with('caja.sucursal')->whereNull('hasta')->orderBy('desde')->orderBy('id')->get();
        $lineas = [
            ['concepto' => 'Plan '.$negocio->plan->etiqueta(), 'detalle' => $suscripcion ? null : 'Pendiente de contratación', 'precio_uf' => $precioPlan],
            ...$sucursales->map(fn (SucursalAdicional $contrato) => [
                'concepto' => 'Sucursal adicional', 'detalle' => $contrato->sucursal->nombre, 'precio_uf' => $contrato->precio_uf,
            ]),
            ...$cajas->map(fn (CajaAdicional $contrato) => [
                'concepto' => 'Caja adicional', 'detalle' => $contrato->caja->nombre.' · '.$contrato->caja->sucursal->nombre, 'precio_uf' => $contrato->precio_uf,
            ]),
            ...$negocio->adicionales()->whereNull('hasta')->get()->map(fn ($contrato) => [
                'concepto' => $contrato->concepto->etiqueta(), 'detalle' => null, 'precio_uf' => $contrato->precio_uf,
            ]),
        ];
        $completo = collect($lineas)->every(fn (array $linea) => $linea['precio_uf'] !== null);
        $precios = Tarifa::where('concepto', ConceptoTarifa::Plan)->get()->mapWithKeys(fn (Tarifa $tarifa) => [$tarifa->plan->value => $tarifa->precio_uf]);

        return [
            'estado' => app(EstadoPlan::class)->resumen($negocio),
            'adicionales_contratados' => $negocio->adicionales()->whereNull('hasta')->get()->map(fn ($contrato) => $contrato->concepto->value)->all(),
            'plan' => [
                'value' => $negocio->plan->value,
                'label' => $negocio->plan->etiqueta(),
                'descripcion' => $negocio->plan->descripcion(),
                'funcionalidades' => $negocio->plan->funcionalidades(),
                'precio_uf' => $precioPlan,
                'desde' => $suscripcion?->desde->toIso8601String(),
            ],
            'sucursales_incluidas' => CobroSucursales::INCLUIDAS,
            'lineas' => $lineas,
            'total_mensual_uf' => $completo ? Tarifa::importe(collect($lineas)->sum(fn (array $linea) => Tarifa::unidades($linea['precio_uf']))) : null,
            'opciones' => array_map(fn (Plan $plan) => [
                'value' => $plan->value,
                'label' => $plan->etiqueta(),
                'descripcion' => $plan->descripcion(),
                'precio_uf' => $suscripcion?->plan === $plan ? $suscripcion->precio_uf : ($precios[$plan->value] ?? null),
                'actual' => $plan === $negocio->plan,
            ], Plan::disponibles($negocio->plan)),
        ];
    }

    public function cotizacion(Request $request, CambioPlan $cambio): JsonResponse
    {
        $datos = $request->validate(['plan' => ['required', 'string', Rule::enum(Plan::class)],
            'adicionales' => ['sometimes', 'array'],
            'excluir' => ['sometimes', 'array'],
            'excluir.*' => ['string', 'distinct', 'regex:/^(sucursal|caja):[a-z0-9]{26}$/i'],
            'adicionales.*' => ['string', 'distinct', Rule::in(array_map(fn ($concepto) => $concepto->value, ConceptoTarifa::adicionales()))]]);

        return response()->json(['data' => $cambio->resumen($this->negocioActual->obtener(), Plan::from($datos['plan']), adicionales: $datos['adicionales'] ?? [], excluir: $datos['excluir'] ?? [])]);
    }
}
