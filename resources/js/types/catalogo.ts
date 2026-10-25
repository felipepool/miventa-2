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

export type UnidadVenta = 'unidad' | 'kg';

export interface Producto {
    id: string;
    sku: string | null;
    codigo_barras: string | null;
    nombre: string;
    descripcion: string | null;
    unidad_venta: UnidadVenta;
    precio: number;
    costo: number | null;
    controla_stock: boolean;
    activo: boolean;
    categoria: OpcionCatalogo | null;
    marca: OpcionCatalogo | null;
}
