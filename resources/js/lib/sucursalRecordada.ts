/** Preferencia por usuario en este navegador; nunca sustituye la autorización del servidor. */
export function leerSucursalRecordada(usuarioId: string): string {
    try {
        return window.localStorage.getItem(`sucursal-recordada:${usuarioId}`) ?? '';
    } catch {
        return '';
    }
}

export function guardarSucursalRecordada(usuarioId: string, sucursalId: string): void {
    try {
        window.localStorage.setItem(`sucursal-recordada:${usuarioId}`, sucursalId);
    } catch {
        // La selección funciona también cuando el navegador impide guardar preferencias.
    }
}
