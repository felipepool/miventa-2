import { useEffect } from 'react';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { Alert } from 'react-bootstrap';
import { useAuth } from '@/lib/auth';
import BarraSuperior from '@/components/layout/BarraSuperior';
import MenuLateral from '@/components/layout/MenuLateral';
import { useMenuLateral } from '@/components/layout/useMenuLateral';

/**
 * Estructura de las páginas de administración: menú lateral fijo y barra
 * superior. Las páginas solo aportan su contenido.
 */
export default function AppLayout() {
    const menu = useMenuLateral();
    const { sesion, refresh } = useAuth();
    const estado = sesion?.contexto.suscripcion;
    useEffect(() => {
        if (!estado?.en_prueba || !estado.prueba_hasta) { return; }
        const retraso = Math.min(2147483647, Math.max(1000, new Date(estado.prueba_hasta).getTime() - Date.now() + 100));
        const timer = window.setTimeout(() => { void refresh().catch(() => {}); }, retraso);
        return () => window.clearTimeout(timer);
    }, [estado?.en_prueba, estado?.prueba_hasta, refresh]);
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
                    {estado && (estado.en_prueba || estado.solo_lectura) && <Alert variant={estado.solo_lectura ? 'warning' : 'info'}>
                        {estado.solo_lectura ? 'Tu prueba terminó. Puedes consultar tus datos, pero no vender ni modificar información operativa.'
                            : `Prueba gratuita: quedan ${estado.dias_restantes} días. Los adicionales están habilitados durante la prueba.`}
                        {sesion?.permisos.includes('gestionar_negocio') && <> <Link to="/plan">{estado.contratado ? 'Ver contratación' : 'Elegir plan y adicionales'}</Link>.</>}
                    </Alert>}
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
