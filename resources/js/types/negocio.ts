export interface ModuloNegocio {
    value: string;
    label: string;
    descripcion: string;
    /** El plan contratado lo incluye: solo entonces se puede activar. */
    incluido: boolean;
    activo: boolean;
}

/** Respuesta de `GET /api/v1/negocio`. */
export interface Negocio {
    /** Fijo: los usuarios lo usan para iniciar sesión. */
    codigo: string;
    nombre: string;
    rut: string | null;
    razon_social: string | null;
    giro: string | null;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    plan: { value: string; label: string; descripcion: string };
    modulos: ModuloNegocio[];
    cierre_ciego: boolean;
    descuento_maximo_cajero: number;
}
