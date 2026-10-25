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
    /** Adicional que el negocio debe tener activo (en prueba o contratado) para ver el ítem. */
    adicional?: string;
}

/** Ítems del menú; crece con cada módulo que se incorpora. */
const ITEMS: ItemMenu[] = [
    { href: '/dashboard', label: 'Inicio', icon: 'speedometer2' },
    { href: '/inventario', label: 'Inventario', icon: 'boxes', permiso: 'gestionar_inventario', adicional: 'inventario' },
];

const CATALOGO: ItemMenu[] = [
    { href: '/productos', label: 'Productos', icon: 'box-seam', permiso: 'gestionar_catalogo' },
    { href: '/categorias', label: 'Categorías', icon: 'collection', permiso: 'gestionar_catalogo' },
    { href: '/marcas', label: 'Marcas', icon: 'tags', permiso: 'gestionar_catalogo' },
];

const ADMINISTRACION: ItemMenu[] = [
    { href: '/negocio', label: 'Mi negocio', icon: 'building-gear', permiso: 'gestionar_negocio' },
    { href: '/plan', label: 'Mi plan', icon: 'stars', permiso: 'gestionar_negocio' },
    { href: '/usuarios', label: 'Usuarios', icon: 'person-badge', permiso: 'gestionar_usuarios' },
    { href: '/sucursales', label: 'Sucursales', icon: 'shop', permiso: 'gestionar_sucursales' },
    { href: '/cajas', label: 'Cajas', icon: 'cash-stack', permiso: 'gestionar_cajas' },
];

/**
 * Menú principal: marca y navegación. Fijo desde pantallas grandes y
 * desplegable (offcanvas) en celular y tablet.
 */
export default function MenuLateral({ abierto, onCerrar }: MenuLateralProps) {
    const { contexto, permisos } = useSesion();
    const { negocio } = contexto;
    const adicionalesActivos = contexto.suscripcion?.adicionales_activos ?? [];
    const visible = (item: ItemMenu) => (!item.permiso || permisos.includes(item.permiso)) && (!item.adicional || adicionalesActivos.includes(item.adicional));
    const { pathname } = useLocation();
    const catalogo = CATALOGO.filter((item) => !item.permiso || permisos.includes(item.permiso));
    const administracion = ADMINISTRACION.filter((item) => !item.permiso || permisos.includes(item.permiso));
    const catalogoActivo = catalogo.some((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
    const administracionActiva = administracion.some((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
    const [catalogoAbierto, setCatalogoAbierto] = useState(true);
    const [administracionAbierta, setAdministracionAbierta] = useState(true);

    useEffect(() => {
        if (catalogoActivo) {
            setCatalogoAbierto(true);
        }
        if (administracionActiva) {
            setAdministracionAbierta(true);
        }
    }, [pathname, catalogoActivo, administracionActiva]);

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
                    {ITEMS.filter(visible).map((item) => (
                        <NavLink key={item.href} to={item.href} className="app-sidebar__link" onClick={onCerrar}>
                            <Icon name={item.icon} />
                            {item.label}
                        </NavLink>
                    ))}
                    {catalogo.length > 0 && (
                        <div className="app-sidebar__group">
                            <button
                                type="button"
                                className={`app-sidebar__link app-sidebar__group-toggle ${catalogoActivo ? 'has-active' : ''}`}
                                aria-expanded={catalogoAbierto}
                                aria-controls="menu-catalogo"
                                onClick={() => setCatalogoAbierto((actual) => !actual)}
                            >
                                <Icon name="box-seam" />
                                <span className="flex-grow-1 text-start">Catálogo</span>
                                <Icon name="chevron-down" className={`app-sidebar__chevron ${catalogoAbierto ? 'is-open' : ''}`} />
                            </button>
                            <Collapse in={catalogoAbierto}>
                                <div id="menu-catalogo">
                                    <div className="app-sidebar__group-items">
                                        {catalogo.map((item) => (
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

