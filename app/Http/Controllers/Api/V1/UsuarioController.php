<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    /**
     * Usuarios del negocio, con búsqueda y filtros por rol y estado.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', Rule::enum(Rol::class)],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $usuarios = User::query()
            ->with(['roles', 'sucursales' => fn ($query) => $query->orderBy('nombre')])
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $buscar) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$buscar}%")
                    ->orWhereLike('username', "%{$buscar}%")
                    ->orWhereLike('email', "%{$buscar}%"),
            ))
            ->when($filtros['rol'] ?? null, fn (Builder $query, string $rol) => $query->role($rol))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('activo', $estado === 'activo'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return UsuarioResource::collection($usuarios);
    }

    public function show(User $usuario): JsonResponse
    {
        return $this->respuesta($usuario);
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $usuario = DB::transaction(function () use ($request): User {
            $usuario = User::create($request->safe()->only(['name', 'username', 'email', 'password']));
            $this->asignarRolesYSucursales($usuario, $request);

            return $usuario;
        });

        return $this->respuesta($usuario->refresh())->setStatusCode(201);
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): JsonResponse
    {
        DB::transaction(function () use ($request, $usuario): void {
            Negocio::whereKey($usuario->negocio_id)->lockForUpdate()->firstOrFail();
            $usuario->refresh()->unsetRelation('roles');

            $editor = $request->user()->fresh();
            abort_unless($editor->puedeIniciarSesion() && $editor->can('gestionar_usuarios'), 403);

            if (! $request->esSoloAdmin() && $usuario->esAdmin()) {
                if ($usuario->is($editor)) {
                    throw ValidationException::withMessages(['roles' => 'No puedes quitarte el rol de administrador.']);
                }

                if ($usuario->esUltimoAdminActivo()) {
                    throw ValidationException::withMessages(['roles' => 'El negocio debe tener al menos un administrador activo.']);
                }
            }

            $usuario->fill($request->safe()->only(['name', 'username', 'email']));

            if ($request->filled('password')) {
                $usuario->password = $request->validated('password');
            }

            $usuario->save();
            $this->asignarRolesYSucursales($usuario, $request);
        });

        return $this->respuesta($usuario);
    }

    /**
     * El admin trabaja en todas las sucursales; los demás guardan sus asignaciones.
     */
    private function asignarRolesYSucursales(User $usuario, StoreUsuarioRequest $request): void
    {
        $sucursalIds = $request->esSoloAdmin()
            ? []
            : Sucursal::whereIn('public_id', $request->validated('sucursales'))->pluck('id')->all();

        $usuario->syncRoles(array_map(fn (Rol $rol) => $rol->value, $request->roles()));
        $usuario->sucursales()->sync($sucursalIds);
    }

    private function respuesta(User $usuario): JsonResponse
    {
        $usuario->unsetRelation('roles')->load(['roles', 'sucursales' => fn ($query) => $query->orderBy('nombre')]);

        return (new UsuarioResource($usuario))->response();
    }
}
