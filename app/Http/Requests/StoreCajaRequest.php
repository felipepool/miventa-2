<?php

namespace App\Http\Requests;

use App\Models\Caja;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCajaRequest extends FormRequest
{
    /** La autorización se aplica en la ruta con gestionar_cajas. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sucursal' => $this->cajaEditada() ? ['prohibited'] : ['required', 'string', 'ulid'],
            'nombre' => ['required', 'string', 'max:50'],
            'acepto_cobro' => ['sometimes', 'boolean'],
            'cotizacion' => ['nullable', 'string', 'size:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim($this->input('nombre'))]);
        }
    }

    protected function cajaEditada(): ?Caja
    {
        return null;
    }
}
