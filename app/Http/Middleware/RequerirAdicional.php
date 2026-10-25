<?php

namespace App\Http\Middleware;

use App\Actions\Planes\EstadoPlan;
use App\Enums\ConceptoTarifa;
use App\Support\Contexto\NegocioActual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el negocio tenga activo un adicional (en prueba o contratado).
 * Sin él, los datos se conservan pero la funcionalidad no está disponible.
 * Uso: `adicional:inventario`.
 */
class RequerirAdicional
{
    public function __construct(private NegocioActual $negocioActual, private EstadoPlan $estadoPlan) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $adicional): Response
    {
        $concepto = ConceptoTarifa::from($adicional);
        $negocio = $this->negocioActual->obtener();

        if ($negocio === null || ! in_array($concepto, $this->estadoPlan->adicionalesActivos($negocio), true)) {
            return response()->json([
                'message' => $concepto->etiqueta().' no está contratado en tu plan. Contrátalo desde Mi plan o consulta con el administrador del negocio.',
                'code' => 'adicional_no_contratado',
                'adicional' => $concepto->value,
            ], 403);
        }

        return $next($request);
    }
}
