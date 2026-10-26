export interface MovimientoCaja {
    id: string;
    tipo: 'ingreso' | 'retiro';
    monto: number;
    motivo: string;
    usuario: string | null;
    created_at: string;
}

export interface ConteoEfectivo {
    valor: number;
    cantidad: number;
}

/** Turno de caja; con el cierre ciego, `monto_esperado` y `diferencia` no llegan al cajero. */
export interface TurnoCaja {
    id: string;
    numero: number;
    caja?: { id: string; nombre: string };
    usuario?: string;
    cerrado_por?: string | null;
    monto_inicial: number;
    nota_apertura: string | null;
    nota_cierre: string | null;
    abierta_at: string;
    cerrada_at: string | null;
    ingresos: number;
    retiros: number;
    ventas_efectivo: number;
    devoluciones_efectivo: number;
    monto_esperado?: number | null;
    monto_contado: number | null;
    diferencia?: number | null;
    conteo: ConteoEfectivo[] | null;
    movimientos?: MovimientoCaja[];
}

export interface CajaDisponible {
    id: string;
    nombre: string;
    ocupada_por: string | null;
    monto_sugerido: number | null;
}

/** Respuesta de `GET /api/v1/caja`. */
export interface MiCaja {
    turno: TurnoCaja | null;
    cajas: CajaDisponible[];
    denominaciones: number[];
    puede_ver_esperado: boolean;
}
