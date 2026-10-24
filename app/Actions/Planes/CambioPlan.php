<?php

namespace App\Actions\Planes;

use App\Enums\ConceptoTarifa;
use App\Enums\Modulo;
use App\Enums\Plan;
use App\Models\Negocio;
use App\Models\NegocioAdicional;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambio de plan desde Mi plan: cotiza el nuevo precio y sus módulos, y lo
 * contrata solo con la aceptación de esas mismas condiciones.
 */
class CambioPlan
{
    public function __construct(private ContratarPlan $contratar) {}

    /**
     * @param  list<string>  $adicionales
     * @param  list<string>  $excluir
     * @return array<string, mixed>
     */
    public function resumen(Negocio $negocio, Plan $plan, bool $bloquear = false, array $adicionales = [], array $excluir = []): array
    {
        if ($bloquear) {
            Tarifa::where('plan', $plan)->orderBy('id')->lockForUpdate()->get();
        }
        $consulta = Tarifa::where('concepto', ConceptoTarifa::Plan)->where('plan', $plan);
        $tarifa = ($bloquear ? $consulta->lockForUpdate() : $consulta)->first();
        $precio = $tarifa?->precio_uf !== null && (float) $tarifa->precio_uf >= 0 ? $tarifa->precio_uf : null;
        $contrato = $negocio->suscripciones()->whereNull('hasta')->first();
        $vigente = $contrato?->id;
        if ($contrato?->plan === $plan) {
            $precio = $contrato->precio_uf;
        }
        sort($adicionales);
        $recursos = app(RecursosContratacion::class)->resumen($negocio, $plan);
        sort($excluir);
        if (array_diff($excluir, array_column($recursos, 'value')) !== []) {
            throw ValidationException::withMessages(['excluir' => 'Los recursos cambiaron. Revisa las sucursales y cajas antes de contratar.']);
        }
        $conservados = array_values(array_filter($recursos, fn ($item) => ! in_array($item['value'], $excluir, true)
            && ! in_array('sucursal:'.$item['sucursal'], $excluir, true)));
        $catalogo = Tarifa::where('plan', $plan)->whereIn('concepto', ConceptoTarifa::adicionales());
        $tarifas = ($bloquear ? $catalogo->lockForUpdate() : $catalogo)->get();
        $contratados = $negocio->adicionales()->whereNull('hasta')->get()->keyBy(fn ($item) => $item->concepto->value);
        $opciones = array_map(function ($concepto) use ($tarifas, $contratados, $adicionales): array {
            $anterior = $contratados->get($concepto->value);

            return ['value' => $concepto->value, 'label' => $concepto->etiqueta(),
                'precio_uf' => $anterior?->precio_uf ?? $tarifas->firstWhere('concepto', $concepto)?->precio_uf,
                'seleccionado' => in_array($concepto->value, $adicionales, true), 'contrato_id' => $anterior?->id];
        }, ConceptoTarifa::adicionales());
        $lineas = [['concepto' => 'Plan '.$plan->etiqueta(), 'detalle' => null, 'precio_uf' => $precio],
            ...array_map(fn ($linea) => array_intersect_key($linea, array_flip(['concepto', 'detalle', 'precio_uf'])), $conservados),
            ...array_map(fn ($item) => ['concepto' => $item['label'], 'detalle' => null, 'precio_uf' => $item['precio_uf']], array_values(array_filter($opciones, fn ($item) => $item['seleccionado'])))];
        $completo = collect($lineas)->every(fn ($linea) => $linea['precio_uf'] !== null && (float) $linea['precio_uf'] >= 0);
        $etiquetas = fn (array $modulos): array => array_map(fn (Modulo $modulo) => ['value' => $modulo->value, 'label' => $modulo->etiqueta()], array_values($modulos));
        $condiciones = [$negocio->id, $negocio->plan->value, $vigente, $plan->value, $tarifa?->public_id, $precio,
            $recursos, $excluir, $opciones, $negocio->enPrueba(), $negocio->prueba_hasta?->toIso8601String()];

        return [
            'recursos' => array_map(fn ($item) => array_intersect_key($item, array_flip(['value', 'sucursal', 'concepto', 'detalle', 'precio_uf'])), $recursos),
            'adicionales' => array_map(fn ($item) => array_diff_key($item, ['contrato_id' => true]), $opciones),
            'lineas' => $lineas,
            'total_mensual_uf' => $completo ? Tarifa::importe(collect($lineas)->sum(fn ($linea) => Tarifa::unidades($linea['precio_uf']))) : null,
            'cobro_desde' => $negocio->enPrueba() ? $negocio->prueba_hasta->toIso8601String() : now()->toDateString(),
            'plan' => $plan->value,
            'precio_uf' => $precio,
            'modulos_agregados' => $etiquetas(array_filter($plan->modulos(), fn (Modulo $modulo) => ! $negocio->plan->incluye($modulo))),
            'modulos_retirados' => $etiquetas(array_filter($negocio->plan->modulos(), fn (Modulo $modulo) => ! $plan->incluye($modulo))),
            'sin_retorno' => ! in_array($negocio->plan, Plan::disponibles(), true),
            'cotizacion' => hash_hmac('sha256', json_encode($condiciones, JSON_THROW_ON_ERROR), config('app.key')),
        ];
    }

    /**
     * Bloquea el negocio para que dos envíos simultáneos no abran contratos duplicados.
     */
    public function cambiar(Negocio $negocio, Plan $plan, User $usuario, bool $aceptado, ?string $cotizacion, array $adicionales = [], array $excluir = []): Suscripcion
    {
        return DB::transaction(function () use ($negocio, $plan, $usuario, $aceptado, $cotizacion, $adicionales, $excluir): Suscripcion {
            $negocio = Negocio::whereKey($negocio->id)->lockForUpdate()->firstOrFail();
            $usuario = $usuario->fresh();
            abort_unless($usuario->puedeIniciarSesion() && $usuario->negocio_id === $negocio->id && $usuario->can('gestionar_negocio'), 403);

            if (! in_array($plan, Plan::disponibles($negocio->plan), true)) {
                throw ValidationException::withMessages(['plan' => 'Elige uno de los planes disponibles para tu negocio.']);
            }
            $resumen = $this->resumen($negocio, $plan, bloquear: true, adicionales: $adicionales, excluir: $excluir);
            if ($resumen['total_mensual_uf'] === null) {
                throw ValidationException::withMessages(['tarifa' => 'Hay un precio sin configurar. Contacta a soporte antes de contratar.']);
            }
            if (! $aceptado) {
                throw ValidationException::withMessages(['acepto_cobro' => 'Acepta el cobro mensual del desglose para continuar.']);
            }
            if ($cotizacion === null || ! hash_equals($resumen['cotizacion'], $cotizacion)) {
                throw ValidationException::withMessages(['cotizacion' => 'Las condiciones han cambiado. Vuelve a revisar el plan antes de aceptar.']);
            }

            $contrato = $this->contratar->ejecutar($negocio, $plan, $usuario, $resumen['precio_uf']);
            $desde = $negocio->enPrueba() ? $negocio->prueba_hasta : now();
            $vigentes = $negocio->adicionales()->whereNull('hasta')->get()->keyBy(fn ($item) => $item->concepto->value);
            foreach (ConceptoTarifa::adicionales() as $concepto) {
                $vigente = $vigentes->get($concepto->value);
                if (! in_array($concepto->value, $adicionales, true)) {
                    $vigente?->update(['hasta' => $desde]);
                } elseif ($vigente === null) {
                    $tarifa = Tarifa::where('plan', $plan)->where('concepto', $concepto)->firstOrFail();
                    (new NegocioAdicional)->forceFill(['negocio_id' => $negocio->id, 'tarifa_id' => $tarifa->id,
                        'desde' => $desde, 'aceptado_por_id' => $usuario->id, 'aceptado_at' => now()])->save();
                }
            }
            app(RecursosContratacion::class)->desactivar($negocio, $excluir);
            app(RecursosContratacion::class)->contratar($negocio, $plan, $usuario);

            return $contrato;
        }, 3);
    }
}
