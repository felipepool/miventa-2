<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferenciasNegocioRequest extends FormRequest
{
    /**
     * La autorización la aplica la ruta con el permiso `gestionar_negocio`.
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
        return [
            'cierre_ciego' => ['required', 'boolean'],
            'descuento_maximo_cajero' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
