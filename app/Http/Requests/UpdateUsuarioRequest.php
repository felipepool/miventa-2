<?php

namespace App\Http\Requests;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUsuarioRequest extends StoreUsuarioRequest
{
    /**
     * La contraseña es opcional: si se envía, el admin la restablece.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ];
    }

    /**
     * Protege a los administradores: nadie se quita su propio rol de admin y
     * el negocio nunca se queda sin un administrador activo.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if (in_array(Rol::Admin->value, (array) $this->input('roles', []), true) || $validator->errors()->has('roles*')) {
                    return;
                }

                $usuario = $this->usuarioEditado();

                if ($usuario->is($this->user()) && $usuario->esAdmin()) {
                    $validator->errors()->add('roles', 'No puedes quitarte el rol de administrador.');
                } elseif ($usuario->esUltimoAdminActivo()) {
                    $validator->errors()->add('roles', 'El negocio debe tener al menos un administrador activo.');
                }
            },
        ];
    }

    protected function usuarioEditado(): User
    {
        return $this->route('usuario');
    }
}
