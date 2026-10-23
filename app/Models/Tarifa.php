<?php

namespace App\Models;

use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Concerns\TienePublicId;
use Database\Factories\TarifaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table('tarifas')]
#[Fillable(['concepto', 'plan', 'precio_uf'])]
class Tarifa extends Model
{
    /** @use HasFactory<TarifaFactory> */
    use HasFactory, TienePublicId;

    protected function casts(): array
    {
        return ['concepto' => ConceptoTarifa::class, 'plan' => Plan::class, 'precio_uf' => 'decimal:4'];
    }

    public static function unidades(string $precio): int
    {
        [$entero, $decimal] = array_pad(explode('.', $precio, 2), 2, '');

        return ((int) $entero * 10000) + (int) str_pad($decimal, 4, '0');
    }

    public static function importe(int $unidades): string
    {
        return intdiv($unidades, 10000).'.'.str_pad((string) ($unidades % 10000), 4, '0', STR_PAD_LEFT);
    }
}
