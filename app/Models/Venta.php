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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    /** @return HasMany<Devolucion, $this> */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class)->orderBy('id');
    }

    /**
     * Líneas devueltas en todas las devoluciones de la venta.
     *
     * @return HasManyThrough<DevolucionItem, Devolucion, $this>
     */
    public function itemsDevueltos(): HasManyThrough
    {
        return $this->hasManyThrough(DevolucionItem::class, Devolucion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function estaAnulada(): bool
    {
        return $this->estado === EstadoVenta::Anulada;
    }

    /**
     * Cantidad devuelta de cada línea, por id de la línea. Usa las devoluciones con sus líneas cargadas.
     *
     * @return array<int, string>
     */
    public function cantidadesDevueltas(): array
    {
        $devueltas = [];

        foreach ($this->devoluciones as $devolucion) {
            foreach ($devolucion->items as $item) {
                $devueltas[$item->venta_item_id] = bcadd($devueltas[$item->venta_item_id] ?? '0', $item->cantidad, 3);
            }
        }

        return $devueltas;
    }

    /**
     * `total` si se devolvió todo lo vendido, `parcial` si solo una parte y `null` sin devoluciones.
     */
    public static function estadoDevolucion(string $vendida, string $devuelta): ?string
    {
        if (bccomp($devuelta, '0', 3) <= 0) {
            return null;
        }

        return bccomp($devuelta, $vendida, 3) >= 0 ? 'total' : 'parcial';
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
            'anulada_at' => 'datetime',
        ];
    }
}
