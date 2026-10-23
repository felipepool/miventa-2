<?php

namespace App\Models;

use App\Enums\ConceptoTarifa;
use App\Enums\Plan;
use App\Models\Concerns\ConservaPrecioContratado;
use App\Models\Concerns\PerteneceANegocio;
use Database\Factories\SucursalAdicionalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('sucursales_adicionales')]
#[Fillable(['hasta'])]
class SucursalAdicional extends Model
{
    /** @use HasFactory<SucursalAdicionalFactory> */
    use ConservaPrecioContratado, HasFactory, PerteneceANegocio;

    public const ConceptoTarifa CONCEPTO = ConceptoTarifa::Sucursal;

    /** @return BelongsTo<Sucursal, $this> */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    protected function casts(): array
    {
        return ['plan' => Plan::class, 'precio_uf' => 'decimal:4', 'desde' => 'immutable_datetime', 'hasta' => 'immutable_datetime', 'aceptado_at' => 'immutable_datetime'];
    }
}
