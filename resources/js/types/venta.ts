import type { TipoSeleccion, UnidadVenta } from './catalogo';

export type MedioPago = 'efectivo' | 'debito' | 'credito' | 'transferencia';

export interface OpcionModificadorPos {
    id: string;
    nombre: string;
    precio_adicional: number;
    cantidad_maxima: number | null;
}

export interface GrupoPos {
    id: string;
    nombre: string;
    tipo_seleccion: TipoSeleccion;
    minimo: number;
    maximo: number | null;
    cantidad_gratis: number | null;
    precio_extra: number;
    modificadores: OpcionModificadorPos[];
}

export interface ProductoPos {
    id: string;
    nombre: string;
    sku: string | null;
    codigo_barras: string | null;
    precio: number;
    unidad_venta: UnidadVenta;
    categoria: { id: string; nombre: string } | null;
    grupos_modificadores: GrupoPos[];
}

/** Respuesta de `GET /api/v1/pos`. */
export interface CatalogoPos {
    productos: ProductoPos[];
    categorias: { id: string; nombre: string }[];
    medios_pago: { value: MedioPago; label: string }[];
    descuento_maximo: number;
    descuento_sin_limite: boolean;
    turno: { id: string; numero: number; caja: string } | null;
}

export interface Descuento {
    tipo: 'porcentaje' | 'monto';
    valor: number;
}

export interface SeleccionModificador {
    id: string;
    cantidad: number;
}

/** Línea del carrito: el servidor vuelve a calcular todo al vender. */
export interface LineaCarrito {
    clave: string;
    producto: ProductoPos;
    /** Cantidad como texto decimal (hasta 3 decimales). */
    cantidad: string;
    modificadores: SeleccionModificador[];
    descuento: Descuento | null;
}

export interface PagoVenta {
    medio: MedioPago;
    etiqueta: string;
    monto: number;
    recibido: number | null;
    vuelto: number;
}

export interface LineaVenta {
    nombre: string;
    sku: string | null;
    unidad_venta: UnidadVenta;
    cantidad: string;
    precio_unitario: number;
    modificadores_unitario: number;
    subtotal: number;
    descuento: number;
    total: number;
    notas: string | null;
    modificadores: { grupo: string; nombre: string; cantidad: number; precio_adicional: number; total: number }[];
}

export interface Venta {
    id: string;
    folio: number;
    numero: number;
    estado: 'pagada' | 'anulada';
    subtotal: number;
    descuento: number;
    descuento_motivo: string | null;
    redondeo: number;
    total: number;
    notas: string | null;
    pagada_at: string;
    vendedor?: string;
    items_count?: number;
    pagos?: PagoVenta[];
    items?: LineaVenta[];
}
