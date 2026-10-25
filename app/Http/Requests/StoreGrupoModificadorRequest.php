<?php

namespace App\Http\Requests;

use App\Enums\TipoSeleccion;
use App\Models\GrupoModificador;
use App\Support\Contexto\NegocioActual;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGrupoModificadorRequest extends FormRequest
{
    /** Precio máximo aceptado para un modificador o un recargo (CLP, IVA incluido). */
    public const int MONTO_MAXIMO = 9_999_999;

    /** Máximo de unidades configurables en un grupo. */
    public const int UNIDADES_MAXIMAS = 50;

    /** Máximo de modificadores en un grupo. */
    public const int MODIFICADORES_MAXIMOS = 50;

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
        $esUnica = $this->input('tipo_seleccion') === TipoSeleccion::Unica->value;

        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('grupos_modificadores', 'nombre')
                ->where('negocio_id', app(NegocioActual::class)->id())->ignore($this->grupoEditado())],
            'referencia' => ['nullable', 'string', 'max:100'],
            'tipo_seleccion' => ['required', 'string', Rule::enum(TipoSeleccion::class)],
            'minimo' => ['required', 'integer', 'min:0', 'max:'.($esUnica ? 1 : self::UNIDADES_MAXIMAS)],
            'maximo' => ['nullable', 'integer', 'min:1', 'max:'.($esUnica ? 1 : self::UNIDADES_MAXIMAS), 'gte:minimo'],
            'cantidad_gratis' => ['nullable', 'integer', 'min:0', 'max:'.self::UNIDADES_MAXIMAS],
            'precio_extra' => ['required', 'integer', 'min:0', 'max:'.self::MONTO_MAXIMO],
            'modificadores' => ['required', 'array', 'min:1', 'max:'.self::MODIFICADORES_MAXIMOS],
            'modificadores.*' => ['array'],
            'modificadores.*.id' => ['nullable', 'string', 'ulid'],
            'modificadores.*.nombre' => ['required', 'string', 'max:100'],
            'modificadores.*.precio_adicional' => ['required', 'integer', 'min:0', 'max:'.self::MONTO_MAXIMO],
            'modificadores.*.cantidad_maxima' => ['nullable', 'integer', 'min:1', 'max:'.self::UNIDADES_MAXIMAS],
            'modificadores.*.activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un grupo de modificadores con este nombre en tu negocio.',
            'maximo.gte' => 'El máximo no puede ser menor que el mínimo.',
            'maximo.max' => 'Un grupo de selección única admite como máximo 1.',
            'minimo.max' => 'Un grupo de selección única admite como mínimo 0 o 1.',
            'modificadores.required' => 'Agrega al menos un modificador.',
            'modificadores.min' => 'Agrega al menos un modificador.',
            'modificadores.*.nombre.required' => 'Escribe el nombre del modificador.',
            'modificadores.*.precio_adicional.integer' => 'El precio adicional debe ser un monto entero en pesos.',
            'precio_extra.integer' => 'El precio extra debe ser un monto entero en pesos.',
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validarReglasCruzadas($validator)];
    }

    protected function prepareForValidation(): void
    {
        $limpiar = fn (mixed $valor) => is_string($valor) ? (trim($valor) === '' ? null : trim($valor)) : $valor;
        $modificadores = $this->input('modificadores');

        if (is_array($modificadores)) {
            $modificadores = array_map(fn (mixed $modificador) => is_array($modificador) ? [
                ...$modificador,
                'nombre' => $limpiar($modificador['nombre'] ?? null),
                'precio_adicional' => ($modificador['precio_adicional'] ?? null) === null || ($modificador['precio_adicional'] ?? null) === '' ? 0 : $modificador['precio_adicional'],
                'cantidad_maxima' => ($modificador['cantidad_maxima'] ?? null) === '' ? null : ($modificador['cantidad_maxima'] ?? null),
                'activo' => $modificador['activo'] ?? true,
            ] : $modificador, array_values($modificadores));
        }

        $this->merge([
            'nombre' => $limpiar($this->input('nombre')),
            'referencia' => $limpiar($this->input('referencia')),
            'maximo' => $this->input('maximo') === '' ? null : $this->input('maximo'),
            'cantidad_gratis' => $this->input('cantidad_gratis') === '' ? null : $this->input('cantidad_gratis'),
            'precio_extra' => $this->input('precio_extra') === null || $this->input('precio_extra') === '' ? 0 : $this->input('precio_extra'),
            'modificadores' => $modificadores,
        ]);
    }

    /**
     * Grupo que se está editando (ninguno al crear).
     */
    protected function grupoEditado(): ?GrupoModificador
    {
        return null;
    }

    /** Reglas que dependen de varios campos: nombres repetidos y mínimo alcanzable. */
    private function validarReglasCruzadas(Validator $validator): void
    {
        $modificadores = $this->input('modificadores');

        if ($validator->errors()->isNotEmpty() || ! is_array($modificadores)) {
            return;
        }

        $nombres = [];
        foreach ($modificadores as $indice => $modificador) {
            $nombre = Str::lower($modificador['nombre']);
            if (isset($nombres[$nombre])) {
                $validator->errors()->add("modificadores.$indice.nombre", 'Este modificador está repetido en el grupo.');
            }
            $nombres[$nombre] = true;
        }

        $activos = count(array_filter($modificadores, fn (array $modificador) => $modificador['activo']));
        if ($activos === 0) {
            $validator->errors()->add('modificadores', 'Deja al menos un modificador activo.');
        } elseif ($this->input('tipo_seleccion') !== TipoSeleccion::Cantidad->value && (int) $this->input('minimo') > $activos) {
            $validator->errors()->add('minimo', 'El mínimo no puede superar los modificadores activos del grupo.');
        }
    }
}
