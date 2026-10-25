<?php

namespace App\Http\Requests;

use App\Enums\MedioPago;
use App\Support\Ventas\Descuentos;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarVentaRequest extends FormRequest
{
    /** Líneas máximas por venta. */
    public const int ITEMS_MAXIMOS = 100;

    /** Cantidad máxima por línea. */
    public const int CANTIDAD_MAXIMA = 99_999;

    /** Monto máximo aceptado para descuentos y pagos (CLP). */
    public const int MONTO_MAXIMO = 99_999_999;

    /**
     * La autorización la aplica la ruta con el permiso `vender`.
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
            'items' => ['required', 'array', 'min:1', 'max:'.self::ITEMS_MAXIMOS],
            'items.*' => ['array'],
            'items.*.producto' => ['required', 'string', 'ulid'],
            'items.*.cantidad' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:'.self::CANTIDAD_MAXIMA],
            'items.*.modificadores' => ['nullable', 'array', 'max:100'],
            'items.*.modificadores.*.id' => ['required', 'string', 'ulid'],
            'items.*.modificadores.*.cantidad' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.descuento' => ['nullable', 'array:tipo,valor'],
            'items.*.descuento.tipo' => ['required_with:items.*.descuento', 'string', Rule::in([Descuentos::PORCENTAJE, Descuentos::MONTO])],
            'items.*.descuento.valor' => ['required_with:items.*.descuento', 'integer', 'min:1', 'max:'.self::MONTO_MAXIMO],
            'items.*.notas' => ['nullable', 'string', 'max:255'],
            'descuento' => ['nullable', 'array:tipo,valor'],
            'descuento.tipo' => ['required_with:descuento', 'string', Rule::in([Descuentos::PORCENTAJE, Descuentos::MONTO])],
            'descuento.valor' => ['required_with:descuento', 'integer', 'min:1', 'max:'.self::MONTO_MAXIMO],
            'motivo_descuento' => ['nullable', 'string', 'max:150'],
            'notas' => ['nullable', 'string', 'max:255'],
            'clave_idempotencia' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'pagos' => ['required', 'array', 'min:1', 'max:'.count(MedioPago::cases())],
            'pagos.*' => ['array'],
            'pagos.*.medio' => ['required', 'string', Rule::enum(MedioPago::class)],
            'pagos.*.monto' => ['nullable', 'integer', 'min:1', 'max:'.self::MONTO_MAXIMO],
            'pagos.*.recibido' => ['nullable', 'integer', 'min:0', 'max:'.self::MONTO_MAXIMO],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un producto a la venta.',
            'items.min' => 'Agrega al menos un producto a la venta.',
            'items.*.cantidad.decimal' => 'La cantidad admite hasta 3 decimales.',
            'items.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'pagos.required' => 'Elige cómo paga el cliente.',
            'pagos.min' => 'Elige cómo paga el cliente.',
            'pagos.*.medio.enum' => 'Elige un medio de pago válido.',
            'descuento.tipo.in' => 'El descuento debe ser un porcentaje o un monto.',
            'descuento.valor.integer' => 'El descuento debe ser un número entero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $items = array_map(fn (mixed $item) => is_array($item) && (is_int($item['cantidad'] ?? null) || is_float($item['cantidad'] ?? null))
                ? [...$item, 'cantidad' => (string) $item['cantidad']] : $item, array_values($items));
        }

        $motivo = $this->input('motivo_descuento');

        $this->merge([
            'items' => $items,
            'motivo_descuento' => is_string($motivo) ? (trim($motivo) === '' ? null : trim($motivo)) : $motivo,
        ]);
    }
}
