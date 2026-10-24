export interface User {
    /** `public_id` del usuario (ULID); el id numérico nunca sale del servidor. */
    id: string;
    name: string;
    username: string;
    email: string | null;
}

export type Rol = 'admin' | 'cajero' | 'cocina' | 'garzon';

export interface OpcionRol {
    value: Rol;
    label: string;
}

export interface Usuario extends User {
    activo: boolean;
    roles: Rol[];
    sucursales: (SucursalResumen & { activa: boolean })[];
    es_usuario_actual: boolean;
}

export interface SucursalResumen {
    id: string;
    nombre: string;
}

export interface Contexto {
    suscripcion: import('./plan').EstadoPlan | null;
    negocio: { codigo: string; nombre: string } | null;
    modulos: string[];
    sucursal: SucursalResumen | null;
    sucursales: (SucursalResumen & { direccion: string | null; es_principal: boolean })[];
}

/** Respuesta de `GET /api/v1/me`. */
export interface Sesion {
    user: User;
    roles: Rol[];
    permisos: string[];
    contexto: Contexto;
}
