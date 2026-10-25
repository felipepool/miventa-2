import { useEffect } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import AvisoSuscripcion from '@/components/layout/AvisoSuscripcion';
import BarraSuperior from '@/components/layout/BarraSuperior';
import MenuLateral from '@/components/layout/MenuLateral';
import { useMenuLateral } from '@/components/layout/useMenuLateral';

/**
 * Estructura de las páginas de administración: menú lateral fijo y barra
 * superior. Las páginas solo aportan su contenido.
 */
export default function AppLayout() {
    const menu = useMenuLateral();
    const { pathname } = useLocation();
    const { cerrar } = menu;

    // Al navegar (incluido el botón atrás) el menú desplegable se cierra.
    // Solo depende de la ruta: `cerrar` se recrea en cada render.
    useEffect(() => {
        cerrar();
    }, [pathname]);

    return (
        <div className="app-shell">
            <MenuLateral abierto={menu.abierto} onCerrar={menu.cerrar} />

            <div className="app-main">
                <BarraSuperior onAbrirMenu={menu.alternar} />

                <main className="app-content">
                    <AvisoSuscripcion />
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
