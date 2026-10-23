import { useEffect, useState } from 'react';
import { Collapse } from 'react-bootstrap';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { Icon } from '@/components/ui';
import { useSesion } from '@/lib/auth';

interface MenuLateralProps {
    abierto: boolean;
    onCerrar: () => void;
}

interface ItemMenu {
    href: string;
    label: string;
    icon: string;
    permiso?: string;
}

/** Ítems del menú; crece con cada módulo que se incorpora. */
const ITEMS: ItemMenu[] = [
    { href: '/dashboard', label: 'Inicio', icon: 'speedometer2' },
];

const ADMINISTRACION: ItemMenu[] = [
    { href: '/usuarios', label: 'Usuarios', icon: 'person-badge', permiso: 'gestionar_usuarios' },
];

/**
 * Menú principal: marca y navegación. Fijo desde pantallas grandes y
 * desplegable (offcanvas) en celular y tablet.
 */
export default function MenuLateral({ abierto, onCerrar }: MenuLateralProps) {
    const { contexto, permisos } = useSesion();
    const { negocio } = contexto;
    const { pathname } = useLocation();
    const administracion = ADMINISTRACION.filter((item) => !item.permiso || permisos.includes(item.permiso));
    const administracionActiva = administracion.some((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
    const [administracionAbierta, setAdministracionAbierta] = useState(true);

    useEffect(() => {
        if (administracionActiva) {
            setAdministracionAbierta(true);
        }
    }, [pathname, administracionActiva]);

    return (
        <>
            <div className={`app-sidebar-backdrop ${abierto ? 'show' : ''}`} onClick={onCerrar} aria-hidden="true" />

            <aside className={`app-sidebar ${abierto ? 'show' : ''}`}>
                <div className="d-flex align-items-start justify-content-between">
                    <Link to="/dashboard" className="app-sidebar__brand" onClick={onCerrar}>
                        <span className="d-flex flex-column lh-sm">
                            {import.meta.env.VITE_APP_NAME}
                            {negocio && <small className="app-sidebar__business">{negocio.nombre}</small>}
                        </span>
                    </Link>
                    <button type="button" className="btn-close btn-close-white mt-2 me-2 d-lg-none" onClick={onCerrar} aria-label="Cerrar menú" />
                </div>

                <nav className="app-sidebar__nav" aria-label="Menú principal">
                    {ITEMS.filter((item) => !item.permiso || permisos.includes(item.permiso)).map((item) => (
                        <NavLink key={item.href} to={item.href} className="app-sidebar__link" onClick={onCerrar}>
                            <Icon name={item.icon} />
                            {item.label}
                        </NavLink>
                    ))}
                    {administracion.length > 0 && (
                        <div className="app-sidebar__group">
                            <button
                                type="button"
                                className={`app-sidebar__link app-sidebar__group-toggle ${administracionActiva ? 'has-active' : ''}`}
                                aria-expanded={administracionAbierta}
                                aria-controls="menu-administracion"
                                onClick={() => setAdministracionAbierta((actual) => !actual)}
                            >
                                <Icon name="gear" />
                                <span className="flex-grow-1 text-start">Administración</span>
                                <Icon name="chevron-down" className={`app-sidebar__chevron ${administracionAbierta ? 'is-open' : ''}`} />
                            </button>
                            <Collapse in={administracionAbierta}>
                                <div id="menu-administracion">
                                    <div className="app-sidebar__group-items">
                                        {administracion.map((item) => (
                                            <NavLink key={item.href} to={item.href} className="app-sidebar__link app-sidebar__link--nested" onClick={onCerrar}>
                                                <Icon name={item.icon} />
                                                {item.label}
                                            </NavLink>
                                        ))}
                                    </div>
                                </div>
                            </Collapse>
                        </div>
                    )}
                </nav>
            </aside>
        </>
    );
}
