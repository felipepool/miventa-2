<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Planes\CobroCajas;
use App\Http\Controllers\Controller;
use App\Http\Resources\CajaResource;
use App\Models\Caja;
use App\Models\Negocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarEstadoCajaController extends Controller
{
    public function __invoke(Request $request, Caja $caja, CobroCajas $cobro): CajaResource
    {
        $activa = $request->validate([
            'activa' => ['required', 'boolean'], 'acepto_cobro' => ['sometimes', 'boolean'],
            'cotizacion' => ['nullable', 'string', 'size:64'],
        ])['activa'];

        DB::transaction(function () use ($request, $caja, $activa, $cobro): void {
            $negocio = Negocio::whereKey($caja->negocio_id)->lockForUpdate()->firstOrFail();
            $caja->refresh();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_cajas'), 403);
            $sucursal = $caja->sucursal;
            if ((bool) $activa && ! $sucursal->activa) {
                throw ValidationException::withMessages(['sucursal' => 'Activa la sucursal antes de crear o activar sus cajas.']);
            }
            if ($caja->activa === (bool) $activa) {
                return;
            }
            if ($activa) {
                $cobro->exigirAceptacion($negocio, $sucursal, $caja, $request->boolean('acepto_cobro'), $request->input('cotizacion'));
                $caja->forceFill(['activa' => true, 'es_incluida' => false])->save();
                $cobro->contratar($negocio, $sucursal, $caja, $editor);
            } else {
                $caja->forceFill(['activa' => false, 'es_incluida' => false])->save();
                $cobro->cerrar($caja);
                $cobro->asegurarIncluida($sucursal);
            }
        }, 3);

        return new CajaResource($caja->refresh()->load(['sucursal', 'contratacionVigente']));
    }
}
