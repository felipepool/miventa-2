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

export type TipoSeleccion = 'unica' | 'multiple' | 'cantidad';

export interface Modificador {
    id: string;
    nombre: string;
    precio_adicional: number;
    cantidad_maxima: number | null;
    orden: number;
    activo: boolean;
}

/** Grupo de modificadores tal como lo entrega el mantenedor, con sus opciones. */
export interface GrupoModificador {
    id: string;
    nombre: string;
    referencia: string | null;
    tipo_seleccion: TipoSeleccion;
    minimo: number;
    maximo: number | null;
    cantidad_gratis: number | null;
    precio_extra: number;
    activo: boolean;
    productos_count?: number;
    modificadores: Modificador[];
}

/** Grupo asignado a un producto o disponible para elegir. */
export interface OpcionGrupoModificador {
    id: string;
    nombre: string;
    tipo_seleccion: TipoSeleccion;
    activo: boolean;
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
    grupos_modificadores?: OpcionGrupoModificador[];
}

