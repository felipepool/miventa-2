<?php

namespace App\Models;

use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Concerns\ConservaPrecioContratado;
use App\Models\Concerns\PerteneceANegocio;
use Database\Factories\CajaAdicionalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('cajas_adicionales')]
#[Fillable(['hasta'])]
class CajaAdicional extends Model
{
    /** @use HasFactory<CajaAdicionalFactory> */
    use ConservaPrecioContratado, HasFactory, PerteneceANegocio;

    public const ConceptoTarifa CONCEPTO = ConceptoTarifa::Caja;

    /** @return BelongsTo<Caja, $this> */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    protected function casts(): array
    {
        return ['plan' => Plan::class, 'precio_uf' => 'decimal:4', 'desde' => 'immutable_datetime', 'hasta' => 'immutable_datetime', 'aceptado_at' => 'immutable_datetime'];
    }
}
