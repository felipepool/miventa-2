<?php

namespace App\Models;

use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Concerns\ConservaPrecioContratado;
use App\Models\Concerns\PerteneceANegocio;
use Database\Factories\SuscripcionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table('suscripciones')]
#[Fillable(['hasta'])]
class Suscripcion extends Model
{
    /** @use HasFactory<SuscripcionFactory> */
    use ConservaPrecioContratado, HasFactory, PerteneceANegocio;

    public const ConceptoTarifa CONCEPTO = ConceptoTarifa::Plan;

    protected function casts(): array
    {
        return ['plan' => Plan::class, 'precio_uf' => 'decimal:4', 'desde' => 'immutable_datetime', 'hasta' => 'immutable_datetime', 'aceptado_at' => 'immutable_datetime'];
    }
}
