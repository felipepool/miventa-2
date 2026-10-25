<?php

namespace App\Http\Requests;

use App\Models\Marca;
use App\Support\Contexto\NegocioActual;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMarcaRequest extends FormRequest
{
    /**
     * La autorización la aplica la ruta con el permiso `gestionar_catalogo`.
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
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('marcas', 'nombre')
                    ->where('negocio_id', app(NegocioActual::class)->id())
                    ->ignore($this->marcaEditada()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['nombre.unique' => 'Ya existe una marca con este nombre en tu negocio.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim((string) $this->input('nombre'))]);
    }

    /**
     * Marca que se está editando (ninguna al crear).
     */
    protected function marcaEditada(): ?Marca
    {
        return null;
    }
}
