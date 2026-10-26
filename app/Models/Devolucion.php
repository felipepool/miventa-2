<?php

namespace App\Models;

use App\Enums\MedioPago;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\PerteneceASucursal;
use App\Models\Concerns\TienePublicId;
use Database\Factories\DevolucionFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Devolución de productos de una venta. El dinero sale del turno abierto de quien devuelve,
 * por el medio elegido; en efectivo baja el esperado de ese turno.
 */
#[Table('devoluciones')]
class Devolucion extends Model
{
    /** @use HasFactory<DevolucionFactory> */
    use HasFactory, PerteneceANegocio, PerteneceASucursal, TienePublicId;

    /** @return BelongsTo<Venta, $this> */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /** @return BelongsTo<TurnoCaja, $this> */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<DevolucionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DevolucionItem::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return ['medio' => MedioPago::class, 'monto' => 'integer', 'reintegra_stock' => 'boolean'];
    }
}
