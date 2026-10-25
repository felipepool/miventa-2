<?php

namespace App\Http\Requests;

use App\Enums\TipoMovimientoCaja;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarMovimientoCajaRequest extends FormRequest
{
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
            'tipo' => ['required', 'string', Rule::enum(TipoMovimientoCaja::class)],
            'monto' => ['required', 'integer', 'min:1', 'max:'.AbrirTurnoRequest::MONTO_MAXIMO],
            'motivo' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.enum' => 'Elige ingreso o retiro.',
            'monto.integer' => 'El monto debe ser un monto entero en pesos.',
            'monto.min' => 'El monto debe ser mayor que cero.',
            'motivo.required' => 'Indica el motivo del movimiento.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $motivo = $this->input('motivo');
        $this->merge(['motivo' => is_string($motivo) ? (trim($motivo) === '' ? null : trim($motivo)) : $motivo]);
    }
}
