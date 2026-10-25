<?php

namespace App\Actions\Ventas;

use App\Actions\Inventario\RegistrarMovimientoStock;
use App\Actions\Planes\EstadoPlan;
use App\Enums\ConceptoTarifa;
use App\Enums\EstadoVenta;
use App\Enums\Modulo;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use App\Support\Contexto\NegocioActual;
use App\Support\Ventas\CalculadoraVenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una venta de mostrador pagada: recalcula todo desde la base de datos, asigna los
 * folios, guarda líneas, modificadores y pagos, y descuenta el stock de la sucursal cuando el
 * negocio tiene el adicional Inventario. Todo o nada.
 *
 * El turno y el negocio se bloquean durante el registro: las ventas quedan en orden con el
 * cierre del turno y los folios no se repiten. El stock puede quedar negativo, porque el
 * producto ya está en manos del cliente.
 */
class RegistrarVenta
{
    public function __construct(
        private CalculadoraVenta $calculadora,
        private NegocioActual $negocioActual,
        private RegistrarMovimientoStock $stock,
        private EstadoPlan $estadoPlan,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  datos validados de la solicitud
     *
     * @throws ValidationException
     */
    public function ejecutar(User $vendedor, array $datos): Venta
    {
        $turnoAbierto = TurnoCaja::abiertos()->where('user_id', $vendedor->id)->first()
            ?? throw ValidationException::withMessages(['turno' => 'Abre tu caja para poder vender.']);

        return DB::transaction(function () use ($vendedor, $datos, $turnoAbierto): Venta {
            $negocio = Negocio::whereKey($this->negocioActual->id())->lockForUpdate()->firstOrFail();
            $clave = $datos['clave_idempotencia'] ?? null;

            if ($clave !== null && $existente = Venta::where('clave_idempotencia', $clave)->first()) {
                return $existente;
            }

            $turno = TurnoCaja::whereKey($turnoAbierto->id)->lockForUpdate()->firstOrFail();

            if (! $turno->estaAbierto()) {
                throw ValidationException::withMessages(['turno' => 'Tu caja ya fue cerrada. Ábrela de nuevo para vender.']);
            }

            $productos = $this->productos($datos['items'], $negocio);
            $entradas = [];

            foreach ($datos['items'] as $indice => $item) {
                $entradas[] = ['producto' => $productos[$item['producto']], 'cantidad' => $this->cantidad($item['cantidad'], $productos[$item['producto']], $indice),
                    'modificadores' => $item['modificadores'] ?? [], 'descuento' => $item['descuento'] ?? null, 'notas' => $item['notas'] ?? null];
            }

            $lineas = $this->calculadora->lineas($entradas, $negocio->tieneModulo(Modulo::Modificadores));
            $descontado = $this->calculadora->descontarVenta($lineas, $datos['descuento'] ?? null);
            $this->validarDescuento($vendedor, $negocio, $descontado, $datos['motivo_descuento'] ?? null);
            $cobro = $this->calculadora->cobro($descontado['base'], $datos['pagos']);

            $venta = new Venta(['notas' => $datos['notas'] ?? null]);
            $venta->forceFill([
                'negocio_id' => $negocio->id,
                'sucursal_id' => $turno->sucursal_id,
                'turno_caja_id' => $turno->id,
                'user_id' => $vendedor->id,
                'numero' => (int) Venta::withoutGlobalScopes()->where('negocio_id', $negocio->id)->max('numero') + 1,
                'numero_sucursal' => (int) Venta::withoutGlobalScopes()->where('sucursal_id', $turno->sucursal_id)->max('numero_sucursal') + 1,
                'estado' => EstadoVenta::Pagada,
                'subtotal' => $descontado['subtotal'],
                'descuento' => $descontado['descuento'],
                'descuento_motivo' => $descontado['descuento'] > 0 ? $datos['motivo_descuento'] : null,
                'redondeo' => $cobro['redondeo'],
                'total' => $cobro['total'],
                'clave_idempotencia' => $clave,
                'pagada_at' => now(),
            ])->save();

            $this->guardarLineas($venta, $descontado['lineas']);
            $venta->pagos()->createMany($cobro['pagos']);
            $this->descontarStock($venta, $negocio, $vendedor, $descontado['lineas']);

            return $venta->refresh();
        }, 3);
    }

    /**
     * Productos de la venta: del negocio, activos, a la venta y con precio.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, Producto>
     *
     * @throws ValidationException
     */
    private function productos(array $items, Negocio $negocio): array
    {
        $encontrados = Producto::with(['gruposModificadores.modificadores'])
            ->whereIn('public_id', array_column($items, 'producto'))->get()->keyBy('public_id');

        foreach ($items as $indice => $item) {
            $producto = $encontrados->get($item['producto']);

            if ($producto === null || ! $producto->activo || ! $producto->se_vende || $producto->precio === null) {
                throw ValidationException::withMessages(["items.$indice.producto" => 'Este producto no está disponible para la venta.']);
            }
        }

        return $encontrados->all();
    }

    /**
     * @throws ValidationException
     */
    private function cantidad(string|int|float $cantidad, Producto $producto, int $indice): string
    {
        $cantidad = (string) $cantidad;

        if ($producto->unidad_venta->value === 'unidad' && bccomp($cantidad, (string) (int) $cantidad, 3) !== 0) {
            throw ValidationException::withMessages(["items.$indice.cantidad" => 'Este producto se vende por unidad: usa una cantidad entera.']);
        }

        return $cantidad;
    }

    /**
     * @param  array{subtotal: int, descuento: int, base: int}  $descontado
     *
     * @throws ValidationException
     */
    private function validarDescuento(User $vendedor, Negocio $negocio, array $descontado, ?string $motivo): void
    {
        if ($descontado['descuento'] <= 0) {
            return;
        }

        if ($descontado['base'] < 1) {
            throw ValidationException::withMessages(['descuento' => 'El descuento no puede cubrir toda la venta.']);
        }

        if ($motivo === null) {
            throw ValidationException::withMessages(['motivo_descuento' => 'Indica el motivo del descuento.']);
        }

        if ($descontado['descuento'] * 100 > $descontado['subtotal'] * $negocio->descuento_maximo_cajero && ! $vendedor->can('descontar_sin_limite')) {
            throw ValidationException::withMessages(['descuento' => "Puedes descontar hasta un {$negocio->descuento_maximo_cajero}% de la venta. Pide a un administrador un descuento mayor."]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function guardarLineas(Venta $venta, array $lineas): void
    {
        foreach ($lineas as $orden => $linea) {
            /** @var Producto $producto */
            $producto = $linea['producto'];
            $item = $venta->items()->create([
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'sku' => $producto->sku,
                'unidad_venta' => $producto->unidad_venta->value,
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'costo_unitario' => $producto->costo,
                'modificadores_unitario' => $linea['modificadores_unitario'],
                'subtotal' => $linea['subtotal'],
                'descuento' => $linea['descuento'],
                'descuento_venta' => $linea['descuento_venta'],
                'notas' => $linea['notas'],
                'orden' => $orden,
            ]);

            foreach ($linea['modificadores'] as $modificador) {
                $item->modificadores()->create([
                    'modificador_id' => $modificador['modificador']->id,
                    'grupo' => $modificador['grupo'],
                    'nombre' => $modificador['modificador']->nombre,
                    'cantidad' => $modificador['cantidad'],
                    'precio_adicional' => $modificador['precio_adicional'],
                    'total' => $modificador['total'],
                ]);
            }
        }
    }

    /**
     * Con el adicional Inventario activo, descuenta el stock de la sucursal de los productos con
     * control de stock. Los productos se recorren en orden fijo para no cruzar bloqueos.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function descontarStock(Venta $venta, Negocio $negocio, User $vendedor, array $lineas): void
    {
        if (! in_array(ConceptoTarifa::Inventario, $this->estadoPlan->adicionalesActivos($negocio), true)) {
            return;
        }

        $consumos = [];

        foreach ($lineas as $linea) {
            /** @var Producto $producto */
            $producto = $linea['producto'];

            if ($producto->controla_stock) {
                $consumos[$producto->id] ??= ['producto' => $producto, 'cantidad' => '0'];
                $consumos[$producto->id]['cantidad'] = bcadd($consumos[$producto->id]['cantidad'], $linea['cantidad'], 3);
            }
        }

        ksort($consumos);

        foreach ($consumos as $consumo) {
            $this->stock->descontarPorVenta($consumo['producto'], $venta->sucursal, $vendedor, $consumo['cantidad'], $venta);
        }
    }
}
