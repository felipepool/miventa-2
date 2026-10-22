import axios from 'axios';
import type { AxiosInstance } from 'axios';

/**
 * Clientes HTTP de la SPA. Ambos usan la cookie de sesión (Sanctum en modo SPA):
 * axios envía la cookie XSRF-TOKEN como cabecera X-XSRF-TOKEN automáticamente.
 *
 * - `api`: API JSON versionada (`/api/v1/...`).
 * - `web`: endpoints de autenticación de Fortify (`/login`, `/logout`, ...).
 */
function crearCliente(baseURL: string): AxiosInstance {
    const cliente = axios.create({
        baseURL,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        withCredentials: true,
        withXSRFToken: true,
    });

    cliente.interceptors.response.use(
        (response) => response,
        (error: unknown) => {
            // 401: sin sesión. 419: el token CSRF expiró junto con la sesión.
            if (axios.isAxiosError(error) && [401, 419].includes(error.response?.status ?? 0)) {
                alExpirarSesion?.();
            }

            return Promise.reject(error);
        },
    );

    return cliente;
}

let alExpirarSesion: (() => void) | null = null;

/**
 * Registra qué hacer cuando el servidor indica que no hay sesión (lo usa el AuthProvider).
 */
export function onSesionExpirada(callback: (() => void) | null): void {
    alExpirarSesion = callback;
}

export const api = crearCliente('/api/v1');

export const web = crearCliente('/');

/**
 * Inicializa la cookie CSRF antes de un POST de autenticación.
 */
export async function prepararCsrf(): Promise<void> {
    await web.get('/sanctum/csrf-cookie');
}

/**
 * Errores de validación (422) de Laravel como `{ campo: 'primer mensaje' }`.
 */
export function erroresDeValidacion(error: unknown): Record<string, string> {
    if (!axios.isAxiosError(error) || error.response?.status !== 422) {
        return {};
    }

    const errores = (error.response.data as { errors?: Record<string, string[]> }).errors ?? {};

    return Object.fromEntries(Object.entries(errores).map(([campo, mensajes]) => [campo, mensajes[0] ?? '']));
}

/**
 * Mensaje para mostrar al usuario cuando la petición falla por algo distinto a validación.
 */
export function mensajeDeError(error: unknown): string {
    if (axios.isAxiosError(error)) {
        if (error.response?.status === 429) {
            return 'Demasiados intentos. Espera un momento y vuelve a intentarlo.';
        }

        if (!error.response) {
            return 'No hay conexión con el servidor. Revisa tu internet.';
        }
    }

    return 'Ocurrió un error inesperado. Intenta nuevamente.';
}
