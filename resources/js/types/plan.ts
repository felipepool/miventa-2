export interface LineaCobro {
    concepto: string;
    detalle: string | null;
    /** `null` si el precio aún no está configurado. */
    precio_uf: string | null;
}

export interface OpcionPlan {
    value: string;
    label: string;
    descripcion: string;
    precio_uf: string | null;
    actual: boolean;
}

/** Respuesta de `GET /api/v1/plan`. */
export interface MiPlan {
    plan: {
        value: string;
        label: string;
        descripcion: string;
        funcionalidades: string[];
        precio_uf: string | null;
        /** Inicio del contrato vigente; `null` si aún no hay uno registrado. */
        desde: string | null;
    };
    sucursales_incluidas: number;
    lineas: LineaCobro[];
    total_mensual_uf: string | null;
    opciones: OpcionPlan[];
}

/** Respuesta de `GET /api/v1/plan/cotizacion`. */
export interface CotizacionPlan {
    plan: string;
    precio_uf: string | null;
    modulos_agregados: { value: string; label: string }[];
    modulos_retirados: { value: string; label: string }[];
    /** El plan actual (Mixto) ya no se ofrece: no se podrá volver a él. */
    sin_retorno: boolean;
    cotizacion: string;
}
