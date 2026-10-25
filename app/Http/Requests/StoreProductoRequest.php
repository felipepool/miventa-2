<?php

namespace App\Http\Requests;

use App\Enums\UnidadVenta;
use App\Models\Producto;
use App\Support\Contexto\NegocioActual;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class StoreProductoRequest extends FormRequest
{
    /** Precio y costo máximos aceptados (CLP). */
    public const int MONTO_MAXIMO = 99_999_999;

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
            'nombre' => ['required', 'string', 'max:150'],
            'categoria' => ['nullable', 'string', 'ulid', $this->existeEnCatalogo('categorias', $this->productoEditado()?->categoria_id)],
            'marca' => ['nullable', 'string', 'ulid', $this->existeEnCatalogo('marcas', $this->productoEditado()?->marca_id)],
            'sku' => ['nullable', 'string', 'max:30', $this->unicoEnNegocio()],
            'codigo_barras' => ['nullable', 'string', 'max:50', $this->unicoEnNegocio()],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'se_vende' => ['required', 'boolean', fn (string $atributo, mixed $valor, Closure $fallar) => $valor || $this->boolean('es_insumo')
                ? null : $fallar('Indica si el producto se vende, es un insumo o ambos.')],
            'es_insumo' => ['required', 'boolean'],
            'unidad_venta' => ['required', Rule::enum(UnidadVenta::class)],
            'precio' => [Rule::requiredIf(fn () => $this->boolean('se_vende')), 'nullable', 'integer', 'min:0', 'max:'.self::MONTO_MAXIMO],
            'costo' => ['nullable', 'integer', 'min:0', 'max:'.self::MONTO_MAXIMO],
            'controla_stock' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria.exists' => 'La categoría seleccionada no está disponible.',
            'marca.exists' => 'La marca seleccionada no está disponible.',
            'sku.unique' => 'Ya existe un producto con este SKU en tu negocio.',
            'codigo_barras.unique' => 'Ya existe un producto con este código de barras en tu negocio.',
            'precio.integer' => 'El precio debe ser un monto entero en pesos.',
            'costo.integer' => 'El costo debe ser un monto entero en pesos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');
        $limpios = ['nombre' => is_string($nombre) ? trim($nombre) : $nombre];

        foreach (['categoria', 'marca', 'sku', 'codigo_barras', 'descripcion', 'costo'] as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $valor = trim($valor);
            }
            $limpios[$campo] = $valor === '' ? null : $valor;
        }

        $this->merge($limpios);
    }

    /**
     * Producto que se está editando (ninguno al crear).
     */
    protected function productoEditado(): ?Producto
    {
        return null;
    }

    /**
     * La categoría o marca debe ser del negocio y estar activa, salvo que sea la
     * que el producto ya tenía.
     */
    private function existeEnCatalogo(string $tabla, ?int $actualId): Exists
    {
        return Rule::exists($tabla, 'public_id')
            ->where('negocio_id', app(NegocioActual::class)->id())
            ->where(fn (Builder $query) => $actualId === null
                ? $query->where('activa', true)
                : $query->where('activa', true)->orWhere('id', $actualId));
    }

    private function unicoEnNegocio(): Unique
    {
        return Rule::unique('productos')
            ->where('negocio_id', app(NegocioActual::class)->id())
            ->ignore($this->productoEditado());
    }
}
