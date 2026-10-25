<?php

namespace App\Models;

use App\Enums\EstadoVenta;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\PerteneceASucursal;
use App\Models\Concerns\TienePublicId;
use Database\Factories\VentaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venta de mostrador, pagada al momento. Los montos son pesos enteros con IVA
 * incluido; las líneas guardan una copia del producto para que el historial no
 * cambie si el producto se edita después.
 */
#[Table('ventas')]
#[Fillable(['notas'])]
class Venta extends Model
{
    /** @use HasFactory<VentaFactory> */
    use HasFactory, PerteneceANegocio, PerteneceASucursal, TienePublicId;

    /** @return BelongsTo<TurnoCaja, $this> */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    /** @return BelongsTo<User, $this> */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<VentaItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class)->orderBy('orden')->orderBy('id');
    }

    /** @return HasMany<Pago, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoVenta::class,
            'numero' => 'integer',
            'numero_sucursal' => 'integer',
            'subtotal' => 'integer',
            'descuento' => 'integer',
            'redondeo' => 'integer',
            'total' => 'integer',
            'pagada_at' => 'datetime',
        ];
    }
}
