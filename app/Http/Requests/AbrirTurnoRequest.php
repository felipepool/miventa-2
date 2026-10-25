<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AbrirTurnoRequest extends FormRequest
{
    /** Monto máximo aceptado (CLP). */
    public const int MONTO_MAXIMO = 99_999_999;

    /**
     * La autorización la aplica la ruta con el permiso `operar_caja`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'caja' => ['required', 'string', 'ulid'],
            'monto_inicial' => ['required', 'integer', 'min:0', 'max:'.self::MONTO_MAXIMO],
            'nota' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'caja.required' => 'Elige una caja.',
            'caja.ulid' => 'La caja no pertenece a la sucursal actual.',
            'monto_inicial.required' => 'Ingresa el monto inicial.',
            'monto_inicial.integer' => 'El monto debe ser un monto entero en pesos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $nota = $this->input('nota');
        $this->merge(['nota' => is_string($nota) ? (trim($nota) === '' ? null : trim($nota)) : $nota]);
    }
}
