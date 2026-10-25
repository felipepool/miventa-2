<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use App\Support\Contexto\NegocioActual;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoriaRequest extends FormRequest
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
                Rule::unique('categorias', 'nombre')
                    ->where('negocio_id', app(NegocioActual::class)->id())
                    ->ignore($this->categoriaEditada()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['nombre.unique' => 'Ya existe una categoría con este nombre en tu negocio.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim((string) $this->input('nombre'))]);
    }

    /**
     * Categoría que se está editando (ninguna al crear).
     */
    protected function categoriaEditada(): ?Categoria
    {
        return null;
    }
}
