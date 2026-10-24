<?php

namespace App\Http\Requests;

use App\Rules\Rut;
use App\Support\Contexto\NegocioActual;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNegocioRequest extends FormRequest
{
    /**
     * La autorización la aplica la ruta con el permiso `gestionar_negocio`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El código del negocio no se incluye: es fijo porque los usuarios lo usan para ingresar.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'rut' => ['nullable', 'string', new Rut, Rule::unique('negocios', 'rut')->ignore(app(NegocioActual::class)->id())],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'giro' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rut.unique' => 'Este RUT ya está registrado en otro negocio.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $rut = trim((string) $this->input('rut'));
        $normalizado = Rut::normalizar($rut);

        // Un texto sin dígitos se normaliza a vacío: se valida tal cual para que no se guarde en blanco.
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'rut' => $rut === '' ? null : ($normalizado === '' ? $rut : $normalizado),
        ]);
    }
}
