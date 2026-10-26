<?php

namespace App\Http\Requests;

use App\Enums\MedioPago;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarDevolucionRequest extends FormRequest
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
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.RegistrarVentaRequest::ITEMS_MAXIMOS],
            'items.*' => ['array'],
            'items.*.linea' => ['required', 'integer', 'min:0'],
            'items.*.cantidad' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:'.RegistrarVentaRequest::CANTIDAD_MAXIMA],
            'medio' => ['required', 'string', Rule::enum(MedioPago::class)],
            'motivo' => ['required', 'string', 'max:150'],
            'reintegrar_stock' => ['required', 'boolean'],
            'clave_idempotencia' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Elige al menos un producto a devolver.',
            'items.min' => 'Elige al menos un producto a devolver.',
            'items.*.cantidad.decimal' => 'La cantidad admite hasta 3 decimales.',
            'items.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'medio.required' => 'Elige cómo se devuelve el dinero.',
            'medio.enum' => 'Elige un medio válido para devolver el dinero.',
            'motivo.required' => 'Indica el motivo de la devolución.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $items = array_map(fn (mixed $item) => is_array($item) && (is_int($item['cantidad'] ?? null) || is_float($item['cantidad'] ?? null))
                ? [...$item, 'cantidad' => (string) $item['cantidad']] : $item, array_values($items));
        }

        $motivo = $this->input('motivo');

        $this->merge([
            'items' => $items,
            'motivo' => is_string($motivo) ? (trim($motivo) === '' ? null : trim($motivo)) : $motivo,
        ]);
    }
}
