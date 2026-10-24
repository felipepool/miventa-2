import type { SucursalResumen } from './user';

export interface Sucursal extends SucursalResumen {
    direccion: string | null;
    telefono: string | null;
    es_principal: boolean;
    activa: boolean;
    usuarios_count: number;
    cajas_count: number;
}

export interface CobroSucursal {
    en_prueba: boolean;
    incluidas: number;
    cajas_incluidas_por_sucursal: number;
    activas: number;
    adicionales: number;
    genera_cobro: boolean;
    cajas_adicionales: number;
    requiere_aceptacion: boolean;
    precio_sucursal_uf: string | null;
    precio_caja_uf: string | null;
    total_mensual_uf: string | null;
    este_mes: { dias: number; dias_mes: number; monto_uf: string | null };
    cotizacion: string;
}
