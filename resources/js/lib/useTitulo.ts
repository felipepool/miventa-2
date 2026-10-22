import { useEffect } from 'react';

const APP_NAME = import.meta.env.VITE_APP_NAME ?? 'MiVenta';

/**
 * Título de la pestaña del navegador: "Página · MiVenta".
 */
export function useTitulo(titulo?: string): void {
    useEffect(() => {
        document.title = titulo ? `${titulo} · ${APP_NAME}` : APP_NAME;
    }, [titulo]);
}
