<?php

namespace App\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use App\Enums\Rol;
use App\Models\User;
use App\Support\Contexto\NegocioActual;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUsuarioRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * La autorización la aplica la ruta con el permiso `gestionar_usuarios`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $negocioId = app(NegocioActual::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9._]+$/',
                Rule::unique('users', 'username')->where('negocio_id', $negocioId)->ignore($this->usuarioEditado()),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->usuarioEditado())],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::enum(Rol::class)],
            'sucursales' => [Rule::requiredIf(fn () => ! $this->esSoloAdmin()), 'array'],
            'sucursales.*' => [
                'string',
                'distinct',
                Rule::exists('sucursales', 'public_id')->where('negocio_id', $negocioId)->where('activa', true),
            ],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * Admin tiene todos los permisos: no se combina con otros roles.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $roles = (array) $this->input('roles', []);

                if (! $validator->errors()->has('roles*') && in_array(Rol::Admin->value, $roles, true) && count($roles) > 1) {
                    $validator->errors()->add('roles', 'El administrador no se combina con otros roles.');
                }
            },
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'El usuario solo puede tener letras minúsculas, números, punto y guion bajo.',
            'roles.required' => 'Elige al menos un rol.',
            'roles.min' => 'Elige al menos un rol.',
            'sucursales.required' => 'Asigna al menos una sucursal a este usuario.',
            'sucursales.*.exists' => 'Una de las sucursales seleccionadas no es válida.',
        ];
    }

    /**
     * Roles validados, en el orden de la enumeración.
     *
     * @return list<Rol>
     */
    public function roles(): array
    {
        $roles = array_map(fn (string $rol) => Rol::from($rol), $this->validated('roles'));

        return array_values(array_filter(Rol::cases(), fn (Rol $rol) => in_array($rol, $roles, true)));
    }

    public function esSoloAdmin(): bool
    {
        return (array) $this->input('roles', []) === [Rol::Admin->value];
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('username');
        $email = $this->input('email');

        if (is_string($email)) {
            $email = trim($email);
            $email = $email === '' ? null : $email;
        }

        $this->merge([
            'username' => is_string($username) ? Str::lower(trim($username)) : $username,
            'email' => $email,
        ]);
    }

    /**
     * Usuario que se está editando (ninguno al crear).
     */
    protected function usuarioEditado(): ?User
    {
        return null;
    }
}
