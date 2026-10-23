<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Planes\CobroSucursales;
use App\Http\Controllers\Controller;
use App\Http\Resources\SucursalResource;
use App\Models\Negocio;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarEstadoSucursalController extends Controller
{
    public function __invoke(Request $request, Sucursal $sucursal, CobroSucursales $cobro): SucursalResource
    {
        $activa = $request->validate([
            'activa' => ['required', 'boolean'], 'acepto_cobro' => ['sometimes', 'boolean'],
            'cotizacion' => ['nullable', 'string', 'size:64'],
        ])['activa'];

        DB::transaction(function () use ($request, $sucursal, $activa, $cobro): void {
            $negocio = Negocio::whereKey($sucursal->negocio_id)->lockForUpdate()->firstOrFail();
            $sucursal->refresh();
            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_sucursales'), 403);
            if (! $activa && $sucursal->es_principal) {
                throw ValidationException::withMessages(['activa' => 'La sucursal principal no se puede desactivar.']);
            }
            if ($sucursal->activa === (bool) $activa) {
                return;
            }
            if ($activa) {
                $cobro->exigirAceptacion($negocio, $sucursal, $request->boolean('acepto_cobro'), $request->input('cotizacion'));
            }
            $sucursal->update(['activa' => $activa]);
            if ($activa) {
                $cobro->contratar($negocio, $sucursal, $editor);
            } else {
                $cobro->cerrar($sucursal);
            }
        }, 3);

        return new SucursalResource($sucursal->loadCount(['usuarios', 'cajas']));
    }
}
