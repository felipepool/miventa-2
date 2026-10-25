<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MedioPago;
use App\Enums\Modulo;
use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\GrupoModificador;
use App\Models\Modificador;
use App\Models\Producto;
use App\Models\TurnoCaja;
use App\Support\Contexto\NegocioActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Datos que necesita la pantalla de venta: productos a la venta con sus modificadores,
 * medios de pago, tope de descuento y el turno abierto del usuario.
 */
class PosController extends Controller
{
    public function __invoke(Request $request, NegocioActual $actual): JsonResponse
    {
        $negocio = $actual->obtener();
        $usaModificadores = $negocio->tieneModulo(Modulo::Modificadores);
        $productos = Producto::with(['categoria', 'gruposModificadores.modificadores'])
            ->where('activo', true)->where('se_vende', true)->whereNotNull('precio')
            ->orderBy('nombre')->orderBy('id')->get();
        $turno = TurnoCaja::abiertos()->with('caja')->where('user_id', $request->user()->id)->first();

        return response()->json(['data' => [
            'productos' => $productos->map(fn (Producto $producto) => [
                ...$producto->datosPublicos('nombre', 'sku', 'codigo_barras', 'precio'),
                'unidad_venta' => $producto->unidad_venta->value,
                'categoria' => $producto->categoria?->datosPublicos('nombre'),
                'grupos_modificadores' => $usaModificadores ? $producto->gruposModificadores->where('activo', true)->values()
                    ->map(fn (GrupoModificador $grupo) => [
                        ...$grupo->datosPublicos('nombre', 'minimo', 'maximo', 'cantidad_gratis', 'precio_extra'),
                        'tipo_seleccion' => $grupo->tipo_seleccion->value,
                        'modificadores' => $grupo->modificadores->where('activo', true)->values()
                            ->map(fn (Modificador $modificador) => $modificador->datosPublicos('nombre', 'precio_adicional', 'cantidad_maxima'))->all(),
                    ])->all() : [],
            ])->all(),
            'categorias' => Categoria::where('activa', true)->orderBy('nombre')->orderBy('id')->get()->map(fn (Categoria $categoria) => $categoria->datosPublicos('nombre'))->all(),
            'medios_pago' => array_map(fn (MedioPago $medio) => ['value' => $medio->value, 'label' => $medio->etiqueta()], MedioPago::cases()),
            'descuento_maximo' => $negocio->descuento_maximo_cajero,
            'descuento_sin_limite' => (bool) $request->user()->can('descontar_sin_limite'),
            'turno' => $turno ? ['id' => $turno->public_id, 'numero' => $turno->numero, 'caja' => $turno->caja->nombre] : null,
        ]]);
    }
}
