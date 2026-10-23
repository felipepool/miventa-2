<?php

namespace App\Http\Requests;

use App\Models\Sucursal;
use App\Support\Contexto\NegocioActual;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSucursalRequest extends FormRequest
{
    /**
     * La autorización la aplica la ruta con el permiso `gestionar_sucursales`.
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
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sucursales', 'nombre')
                    ->where('negocio_id', app(NegocioActual::class)->id())
                    ->ignore($this->sucursalEditada()),
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'acepto_cobro' => ['sometimes', 'boolean'],
            'cotizacion' => ['nullable', 'string', 'size:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
        ]);
    }

    /**
     * Sucursal que se está editando (ninguna al crear).
     */
    protected function sucursalEditada(): ?Sucursal
    {
        return null;
    }
}
