<?php

namespace App\Support\Modificadores;

use App\Enums\TipoSeleccion;
use App\Models\GrupoModificador;
use App\Models\Modificador;

/**
 * Valida una selección de modificadores contra las reglas de su grupo y calcula cuánto
 * se cobra por ella. Es la fuente de verdad: la pantalla muestra el mismo cálculo en vivo,
 * pero siempre se recalcula aquí antes de guardar una venta.
 *
 * Cada unidad cobra el `precio_adicional` de su modificador. Si el grupo tiene
 * `cantidad_gratis`, todas las unidades cuentan para el cupo y cada unidad que lo supera
 * cobra además `precio_extra`, en el orden en que se agregaron.
 */
class CalculadoraModificadores
{
    /**
     * @param  list<array{modificador: string, cantidad: int}>  $seleccion  public_id de cada modificador, en el orden en que se agregó
     * @return array{lineas: list<array{modificador: Modificador, cantidad: int, total: int}>, unidades: int, total: int}
     *
     * @throws SeleccionModificadoresInvalida
     */
    public function calcular(GrupoModificador $grupo, array $seleccion): array
    {
        $disponibles = $grupo->modificadores->where('activo', true)->keyBy('public_id');
        $vistos = [];
        $lineas = [];

        foreach ($seleccion as ['modificador' => $publicId, 'cantidad' => $cantidad]) {
            $modificador = $disponibles->get($publicId)
                ?? throw new SeleccionModificadoresInvalida("Una de las opciones de \"{$grupo->nombre}\" no está disponible.");

            if (isset($vistos[$publicId])) {
                throw new SeleccionModificadoresInvalida("\"{$modificador->nombre}\" está repetido en \"{$grupo->nombre}\".");
            }

            $vistos[$publicId] = true;
            $this->validarCantidad($grupo, $modificador, $cantidad);
            $lineas[] = ['modificador' => $modificador, 'cantidad' => $cantidad];
        }

        $unidades = array_sum(array_column($lineas, 'cantidad'));
        $this->validarTotalUnidades($grupo, $unidades);

        return $this->cobrar($grupo, $lineas, $unidades);
    }

    private function validarCantidad(GrupoModificador $grupo, Modificador $modificador, int $cantidad): void
    {
        if ($cantidad < 1) {
            throw new SeleccionModificadoresInvalida("La cantidad de \"{$modificador->nombre}\" no es válida.");
        }

        if ($grupo->tipo_seleccion !== TipoSeleccion::Cantidad && $cantidad > 1) {
            throw new SeleccionModificadoresInvalida("\"{$modificador->nombre}\" solo se puede elegir una vez.");
        }

        if ($modificador->cantidad_maxima !== null && $cantidad > $modificador->cantidad_maxima) {
            throw new SeleccionModificadoresInvalida("Máximo {$modificador->cantidad_maxima} de \"{$modificador->nombre}\".");
        }
    }

    private function validarTotalUnidades(GrupoModificador $grupo, int $unidades): void
    {
        $maximo = $grupo->tipo_seleccion === TipoSeleccion::Unica ? 1 : $grupo->maximo;

        if ($unidades < $grupo->minimo) {
            throw new SeleccionModificadoresInvalida($grupo->minimo === 1
                ? "Elige una opción en \"{$grupo->nombre}\"."
                : "Elige al menos {$grupo->minimo} en \"{$grupo->nombre}\".");
        }

        if ($maximo !== null && $unidades > $maximo) {
            throw new SeleccionModificadoresInvalida("Puedes elegir hasta {$maximo} en \"{$grupo->nombre}\".");
        }
    }

    /**
     * @param  list<array{modificador: Modificador, cantidad: int}>  $lineas
     * @return array{lineas: list<array{modificador: Modificador, cantidad: int, total: int}>, unidades: int, total: int}
     */
    private function cobrar(GrupoModificador $grupo, array $lineas, int $unidades): array
    {
        $unidadesContadas = 0;
        $total = 0;

        foreach ($lineas as $indice => $linea) {
            $conRecargo = $grupo->cantidad_gratis === null
                ? 0
                : max(0, min($linea['cantidad'], $unidadesContadas + $linea['cantidad'] - $grupo->cantidad_gratis));
            $totalLinea = $linea['cantidad'] * $linea['modificador']->precio_adicional + $conRecargo * $grupo->precio_extra;
            $lineas[$indice]['total'] = $totalLinea;
            $unidadesContadas += $linea['cantidad'];
            $total += $totalLinea;
        }

        return ['lineas' => $lineas, 'unidades' => $unidades, 'total' => $total];
    }
}
