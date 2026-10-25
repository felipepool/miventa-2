import type { OpcionCatalogo, UnidadVenta } from './catalogo';

export type TipoMovimiento = 'entrada' | 'salida' | 'ajuste';

/** Tipos del historial: además de los manuales, las ventas descuentan stock. */
export type TipoMovimientoHistorial = TipoMovimiento | 'venta';

/** Producto con sus existencias en la sucursal actual; las cantidades llegan como texto decimal. */
export interface ItemInventario {
    id: string;
    sku: string | null;
    codigo_barras: string | null;
    nombre: string;
    unidad_venta: UnidadVenta;
    activo: boolean;
    cantidad: string;
    stock_minimo: string;
    stock_bajo: boolean;
}

export interface MovimientoStock {
    id: string;
    tipo: TipoMovimientoHistorial;
    cantidad: string;
    stock_resultante: string;
    motivo: string | null;
    usuario: string | null;
    created_at: string;
}

export interface ListadoInventario {
    categorias: OpcionCatalogo[];
}

