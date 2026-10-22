import { Link, NavLink } from 'react-router-dom';
import { Icon } from '@/components/ui';

interface MenuLateralProps {
    abierto: boolean;
    onCerrar: () => void;
}

interface ItemMenu {
    href: string;
    label: string;
    icon: string;
}

/** Ítems del menú; crece con cada módulo que se incorpora. */
const ITEMS: ItemMenu[] = [{ href: '/dashboard', label: 'Inicio', icon: 'speedometer2' }];

/**
 * Menú principal: marca y navegación. Fijo desde pantallas grandes y
 * desplegable (offcanvas) en celular y tablet.
 */
export default function MenuLateral({ abierto, onCerrar }: MenuLateralProps) {
    return (
        <>
            <div className={`app-sidebar-backdrop ${abierto ? 'show' : ''}`} onClick={onCerrar} aria-hidden="true" />

            <aside className={`app-sidebar ${abierto ? 'show' : ''}`}>
                <div className="d-flex align-items-start justify-content-between">
                    <Link to="/dashboard" className="app-sidebar__brand" onClick={onCerrar}>
                        {import.meta.env.VITE_APP_NAME}
                    </Link>
                    <button type="button" className="btn-close btn-close-white mt-2 me-2 d-lg-none" onClick={onCerrar} aria-label="Cerrar menú" />
                </div>

                <nav className="app-sidebar__nav" aria-label="Menú principal">
                    {ITEMS.map((item) => (
                        <NavLink key={item.href} to={item.href} className="app-sidebar__link" onClick={onCerrar}>
                            <Icon name={item.icon} />
                            {item.label}
                        </NavLink>
                    ))}
                </nav>
            </aside>
        </>
    );
}
