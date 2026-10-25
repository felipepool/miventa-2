<?php

namespace App\Http\Requests;

use App\Support\Caja\Denominaciones;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cierre de un turno: se envía el conteo por billetes y monedas, o el total contado directo.
 */
class CerrarTurnoRequest extends FormRequest
{
    /**
     * La autorización la aplica la ruta.
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
            'conteo' => ['nullable', 'array:'.implode(',', Denominaciones::VALORES)],
            'conteo.*' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'monto_contado' => ['required_without:conteo', 'nullable', 'integer', 'min:0', 'max:'.AbrirTurnoRequest::MONTO_MAXIMO],
            'nota' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conteo.array' => 'El conteo tiene billetes o monedas que no existen.',
            'monto_contado.required_without' => 'Ingresa el efectivo contado.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $nota = $this->input('nota');
        $this->merge(['nota' => is_string($nota) ? (trim($nota) === '' ? null : trim($nota)) : $nota]);
    }
}
