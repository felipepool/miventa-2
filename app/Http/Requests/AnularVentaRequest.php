<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnularVentaRequest extends FormRequest
{
    /**
     * La autorización la aplica la ruta con el permiso `anular_ventas`.
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
        return ['motivo' => ['required', 'string', 'max:150']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['motivo.required' => 'Indica el motivo de la anulación.'];
    }

    protected function prepareForValidation(): void
    {
        $motivo = $this->input('motivo');

        $this->merge(['motivo' => is_string($motivo) ? (trim($motivo) === '' ? null : trim($motivo)) : $motivo]);
    }
}
