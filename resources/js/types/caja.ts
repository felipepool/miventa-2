import type { SucursalResumen } from './user';

export interface SucursalCaja extends SucursalResumen {
    activa: boolean;
    es_principal: boolean;
}

export interface Caja {
    id: string;
    nombre: string;
    activa: boolean;
    es_incluida: boolean;
    sucursal: SucursalCaja;
    precio_contratado_uf: string | null;
}

export interface CobroCaja {
    requiere_aceptacion: boolean;
    precio_caja_uf: string | null;
    total_mensual_uf: string | null;
    este_mes: { dias: number; monto_uf: string | null };
    cotizacion: string;
}
