<?php

namespace App\Http\Requests;

use App\Actions\Inventario\RegistrarMovimientoStock;
use App\Enums\UnidadVenta;
use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarMovimientoStockRequest extends FormRequest
{
    /** Tope de cantidad por movimiento (cabe en la columna decimal(14,3)). */
    public const int CANTIDAD_MAXIMA = 99_999_999;

    /**
     * La autorización la aplica la ruta con el permiso `gestionar_inventario`.
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
        $producto = $this->route('producto');
        $decimales = $producto instanceof Producto && $producto->unidad_venta === UnidadVenta::Unidad ? 'decimal:0' : 'decimal:0,3';

        return [
            'tipo' => ['required', 'string', Rule::in(RegistrarMovimientoStock::TIPOS)],
            'cantidad' => ['required', 'numeric', $decimales, $this->input('tipo') === 'ajuste' ? 'min:0' : 'gt:0', 'max:'.self::CANTIDAD_MAXIMA],
            'motivo' => [Rule::requiredIf(fn () => in_array($this->input('tipo'), ['salida', 'ajuste'], true)), 'nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'Elige entrada, salida o ajuste.',
            'cantidad.decimal' => 'Este producto se mueve en cantidades enteras o con hasta 3 decimales, según su unidad.',
            'cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'motivo.required' => 'Indica el motivo del movimiento.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $motivo = $this->input('motivo');
        $cantidad = $this->input('cantidad');

        $this->merge([
            'motivo' => is_string($motivo) ? (trim($motivo) === '' ? null : trim($motivo)) : $motivo,
            'cantidad' => is_int($cantidad) || is_float($cantidad) ? (string) $cantidad : $cantidad,
        ]);
    }
}
