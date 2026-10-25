/** Marca o categoría tal como la entrega el mantenedor. */
export interface ElementoCatalogo {
    id: string;
    nombre: string;
    activa: boolean;
    productos_count: number;
}

/** Marca o categoría asociada a un producto o disponible para elegir. */
export interface OpcionCatalogo {
    id: string;
    nombre: string;
    activa: boolean;
}

export type UnidadVenta = 'unidad' | 'kg' | 'g' | 'l' | 'ml' | 'm';

export interface Producto {
    id: string;
    sku: string | null;
    codigo_barras: string | null;
    nombre: string;
    descripcion: string | null;
    se_vende: boolean;
    es_insumo: boolean;
    unidad_venta: UnidadVenta;
    precio: number | null;
    costo: number | null;
    controla_stock: boolean;
    activo: boolean;
    categoria: OpcionCatalogo | null;
    marca: OpcionCatalogo | null;
}
