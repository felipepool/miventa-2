<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Valida un RUT chileno en formato normalizado `12345678-K` (sin puntos),
 * incluido su dígito verificador (módulo 11).
 */
class Rut implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(\d{7,8})-([\dK])$/', $value, $partes)) {
            $fail('El :attribute no tiene un formato válido (ej. 12.345.678-5).');

            return;
        }

        if (self::digitoVerificador($partes[1]) !== $partes[2]) {
            $fail('El :attribute no es válido.');
        }
    }

    /**
     * Quita puntos y espacios y deja el dígito verificador en mayúscula con guion: `12345678-K`.
     */
    public static function normalizar(string $rut): string
    {
        $limpio = strtoupper((string) preg_replace('/[^0-9kK]/', '', $rut));

        if (strlen($limpio) < 2) {
            return $limpio;
        }

        return substr($limpio, 0, -1).'-'.substr($limpio, -1);
    }

    public static function digitoVerificador(string $cuerpo): string
    {
        $suma = 0;
        $multiplicador = 2;

        foreach (array_reverse(str_split($cuerpo)) as $digito) {
            $suma += (int) $digito * $multiplicador;
            $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
        }

        return match ($resto = 11 - ($suma % 11)) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }
}
