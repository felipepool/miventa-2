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
    /** Posición de la línea en la venta; identifica la línea al devolverla. */
    linea: number;
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
    /** Cantidad ya devuelta; solo viene en el detalle. */
    devuelta: string | null;
    modificadores: { grupo: string; nombre: string; cantidad: number; precio_adicional: number; total: number }[];
}

export interface DevolucionVenta {
    id: string;
    monto: number;
    motivo: string;
    reintegra_stock: boolean;
    medio: MedioPago;
    etiqueta: string;
    usuario: string;
    created_at: string;
    items: { nombre: string; unidad_venta: UnidadVenta; cantidad: string; monto: number }[];
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
    anulada_at: string | null;
    motivo_anulacion: string | null;
    anulada_por?: string | null;
    /** Si el turno de la venta sigue abierto; solo viene en el detalle. */
    turno_abierto?: boolean;
    devolucion: 'parcial' | 'total' | null;
    total_devuelto?: number;
    vendedor?: string;
    items_count?: number;
    pagos?: PagoVenta[];
    items?: LineaVenta[];
    devoluciones?: DevolucionVenta[];
}

/** Comprobante interno de una venta, tal como se imprime o se comparte. */
export interface Comprobante {
    negocio: { nombre: string; rut: string | null; direccion: string | null; telefono: string | null };
    sucursal: string;
    folio: number;
    fecha: string;
    cajero: string;
    estado: 'pagada' | 'anulada';
    anulada_at: string | null;
    subtotal: number;
    descuento: number;
    redondeo: number;
    total: number;
    items: { nombre: string; unidad_venta: UnidadVenta; cantidad: string; precio_unitario: number; subtotal: number; descuento: number; total: number;
        modificadores: { nombre: string; cantidad: number; total: number }[] }[];
    pagos: { etiqueta: string; monto: number; recibido: number | null; vuelto: number }[];
    devoluciones: { fecha: string; etiqueta: string; monto: number; items: { nombre: string; unidad_venta: UnidadVenta; cantidad: string }[] }[];
}

/** Respuesta de `GET /api/v1/ventas/{venta}/enlace-comprobante`. */
export interface ComprobanteConEnlace {
    comprobante: Comprobante;
    enlace: string;
    expira_at: string;
}
