import { useEffect, useState } from 'react';

/**
 * Estado del menú lateral desplegable: se cierra con Esc y bloquea el scroll
 * de la página mientras está abierto.
 */
export function useMenuLateral() {
    const [abierto, setAbierto] = useState(false);

    useEffect(() => {
        if (!abierto) {
            return;
        }

        const cerrarConEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setAbierto(false);
            }
        };

        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', cerrarConEscape);

        return () => {
            document.body.classList.remove('overflow-hidden');
            document.removeEventListener('keydown', cerrarConEscape);
        };
    }, [abierto]);

    return {
        abierto,
        abrir: () => setAbierto(true),
        cerrar: () => setAbierto(false),
        alternar: () => setAbierto((actual) => !actual),
    };
}
