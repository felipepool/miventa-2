import { useEffect } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { OverlayTrigger, Tooltip } from 'react-bootstrap';
import AvisoSuscripcion from '@/components/layout/AvisoSuscripcion';
import BarraSuperior from '@/components/layout/BarraSuperior';
import MenuLateral from '@/components/layout/MenuLateral';
import { useMenuLateral } from '@/components/layout/useMenuLateral';
import { Icon } from '@/components/ui';
import { useSesion } from '@/lib/auth';

interface PantallaVentas {
    href: string;
    label: string;
    icon: string;
    permiso: string;
}

/** Pantallas del trabajo diario de caja; agrega aquí cada pantalla nueva del punto de venta. */
const PANTALLAS: PantallaVentas[] = [
    { href: '/pos', label: 'Vender', icon: 'upc-scan', permiso: 'vender' },
    { href: '/ventas', label: 'Historial', icon: 'receipt', permiso: 'vender' },
    { href: '/caja', label: 'Mi caja', icon: 'cash-stack', permiso: 'operar_caja' },
    { href: '/turnos', label: 'Turnos', icon: 'clock-history', permiso: 'operar_caja' },
];

/**
 * Estructura del punto de venta: una barra delgada de íconos con las pantallas
 * de caja (abajo, en celular y tablet) y el menú principal oculto, que se
 * despliega sobre la pantalla con el botón ☰. Deja todo el ancho para operar.
 */
export default function VentasLayout() {
    const menu = useMenuLateral();
    const { permisos } = useSesion();
    const { pathname } = useLocation();
    const { cerrar } = menu;
    const pantallas = PANTALLAS.filter((pantalla) => permisos.includes(pantalla.permiso));

    useEffect(() => {
        cerrar();
    }, [pathname]);

    return (
        <div className="app-shell app-shell--ventas">
            <MenuLateral abierto={menu.abierto} onCerrar={menu.cerrar} flotante />

            <nav className="ventas-rail" aria-label="Pantallas del punto de venta">
                <button type="button" className="ventas-rail__link ventas-rail__menu" onClick={menu.abrir} aria-label="Abrir menú principal">
                    <Icon name="list" />
                </button>
                {pantallas.map((pantalla) => (
                    <OverlayTrigger key={pantalla.href} placement="right" overlay={<Tooltip>{pantalla.label}</Tooltip>}>
                        <NavLink to={pantalla.href} className={({ isActive }) => `ventas-rail__link ${isActive ? 'active' : ''}`} aria-label={pantalla.label}>
                            <Icon name={pantalla.icon} />
                        </NavLink>
                    </OverlayTrigger>
                ))}
            </nav>

            <div className="app-main">
                <BarraSuperior onAbrirMenu={menu.abrir} />

                <main className="app-content">
                    <AvisoSuscripcion />
                    <Outlet />
                </main>
            </div>

            <nav className="ventas-barra" aria-label="Pantallas del punto de venta">
                {pantallas.map((pantalla) => (
                    <NavLink key={pantalla.href} to={pantalla.href} className={({ isActive }) => `ventas-barra__link ${isActive ? 'active' : ''}`}>
                        <Icon name={pantalla.icon} />
                        <span>{pantalla.label}</span>
                    </NavLink>
                ))}
            </nav>
        </div>
    );
}

