<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsuarioResource;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarEstadoUsuarioController extends Controller
{
    /**
     * Activa o desactiva un usuario. Los usuarios no se eliminan para conservar
     * el historial (ventas, cajas) asociado a ellos.
     */
    public function __invoke(Request $request, User $usuario): UsuarioResource
    {
        $activo = $request->validate(['activo' => ['required', 'boolean']])['activo'];

        DB::transaction(function () use ($request, $usuario, $activo): void {
            Negocio::whereKey($usuario->negocio_id)->lockForUpdate()->firstOrFail();
            $usuario->refresh()->unsetRelation('roles');

            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_usuarios'), 403);

            if (! $activo && $usuario->is($editor)) {
                throw ValidationException::withMessages(['activo' => 'No puedes desactivar tu propio usuario.']);
            }

            if (! $activo && $usuario->esUltimoAdminActivo()) {
                throw ValidationException::withMessages(['activo' => 'El negocio debe tener al menos un administrador activo.']);
            }

            $usuario->update(['activo' => $activo]);
        });

        return new UsuarioResource($usuario->load(['roles', 'sucursales' => fn ($query) => $query->orderBy('nombre')]));
    }
}
