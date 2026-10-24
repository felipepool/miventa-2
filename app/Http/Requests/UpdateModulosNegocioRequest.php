<?php

namespace App\Http\Requests;

use App\Enums\Modulo;
use App\Support\Contexto\NegocioActual;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModulosNegocioRequest extends FormRequest
{
    /**
     * La autorización la aplica la ruta con el permiso `gestionar_negocio`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Solo se pueden activar los módulos que incluye el plan contratado.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'modulos' => ['present', 'array'],
            'modulos.*' => [
                'string',
                'distinct',
                Rule::enum(Modulo::class),
                Rule::in(array_map(fn (Modulo $modulo) => $modulo->value, app(NegocioActual::class)->obtener()->plan->modulos())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modulos.*.in' => 'Tu plan no incluye este módulo.',
        ];
    }
}
