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
    estado: EstadoPlan;
    adicionales_contratados: string[];
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
    recursos: { value: string; sucursal: string; concepto: string; detalle: string; precio_uf: string | null }[];
    adicionales: { value: string; label: string; precio_uf: string | null; seleccionado: boolean }[];
    lineas: LineaCobro[];
    total_mensual_uf: string | null;
    cobro_desde: string;
    plan: string;
    precio_uf: string | null;
    modulos_agregados: { value: string; label: string }[];
    modulos_retirados: { value: string; label: string }[];
    /** El plan actual (Mixto) ya no se ofrece: no se podrá volver a él. */
    sin_retorno: boolean;
    cotizacion: string;
}

export interface EstadoPlan {
    en_prueba: boolean;
    prueba_hasta: string | null;
    dias_restantes: number;
    solo_lectura: boolean;
    contratado: boolean;
    cobro_desde: string | null;
    adicionales_activos: string[];
    facturacion: { ambiente: 'certificacion'; valor_tributario: false; aviso: string };
}
