import { useEffect, useState } from 'react';
import { Collapse } from 'react-bootstrap';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { Icon } from '@/components/ui';
import { useSesion } from '@/lib/auth';

interface MenuLateralProps {
    abierto: boolean;
    onCerrar: () => void;
    /** Siempre oculto y desplegable sobre el contenido (layout del punto de venta); si no, fijo desde pantallas grandes. */
    flotante?: boolean;
}

interface ItemMenu {
    href: string;
    label: string;
    icon: string;
    permiso?: string;
    /** Adicional que el negocio debe tener activo (en prueba o contratado) para ver el ítem. */
    adicional?: string;
    /** Módulo del plan que el negocio debe tener activo para ver el ítem. */
    modulo?: string;
}

type GrupoMenu = 'catalogo' | 'administracion';

/** Ítems del menú; crece con cada módulo que se incorpora. */
const ITEMS: ItemMenu[] = [
    { href: '/dashboard', label: 'Inicio', icon: 'speedometer2' },
    { href: '/pos', label: 'Punto de venta', icon: 'upc-scan', permiso: 'vender' },
    { href: '/inventario', label: 'Inventario', icon: 'boxes', permiso: 'gestionar_inventario', adicional: 'inventario' },
];

const CATALOGO: ItemMenu[] = [
    { href: '/productos', label: 'Productos', icon: 'box-seam', permiso: 'gestionar_catalogo' },
    { href: '/categorias', label: 'Categorías', icon: 'collection', permiso: 'gestionar_catalogo' },
    { href: '/marcas', label: 'Marcas', icon: 'tags', permiso: 'gestionar_catalogo' },
    { href: '/modificadores', label: 'Modificadores', icon: 'sliders', permiso: 'gestionar_catalogo', modulo: 'modificadores' },
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
export default function MenuLateral({ abierto, onCerrar, flotante = false }: MenuLateralProps) {
    const { contexto, permisos } = useSesion();
    const { negocio } = contexto;
    const adicionalesActivos = contexto.suscripcion?.adicionales_activos ?? [];
    const visible = (item: ItemMenu) => (!item.permiso || permisos.includes(item.permiso)) && (!item.adicional || adicionalesActivos.includes(item.adicional)) && (!item.modulo || contexto.modulos.includes(item.modulo));
    const { pathname } = useLocation();
    const modificador = flotante ? 'app-sidebar--flotante' : '';
    const catalogo = CATALOGO.filter(visible);
    const administracion = ADMINISTRACION.filter((item) => !item.permiso || permisos.includes(item.permiso));
    const catalogoActivo = catalogo.some((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
    const administracionActiva = administracion.some((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
    const grupoActivo: GrupoMenu | null = catalogoActivo ? 'catalogo' : administracionActiva ? 'administracion' : null;
    // Solo un grupo del menú permanece desplegado a la vez.
    const [grupoAbierto, setGrupoAbierto] = useState<GrupoMenu | null>(grupoActivo);
    const catalogoAbierto = grupoAbierto === 'catalogo';
    const administracionAbierta = grupoAbierto === 'administracion';
    const alternarGrupo = (grupo: GrupoMenu) => setGrupoAbierto((actual) => (actual === grupo ? null : grupo));

    useEffect(() => {
        if (grupoActivo) {
            setGrupoAbierto(grupoActivo);
        }
    }, [pathname, grupoActivo]);

    return (
        <>
            <div className={`app-sidebar-backdrop ${modificador} ${abierto ? 'show' : ''}`} onClick={onCerrar} aria-hidden="true" />

            <aside className={`app-sidebar ${modificador} ${abierto ? 'show' : ''}`} inert={flotante && !abierto ? true : undefined}>
                <div className="d-flex align-items-start justify-content-between">
                    <Link to="/dashboard" className="app-sidebar__brand" onClick={onCerrar}>
                        <span className="d-flex flex-column lh-sm">
                            {import.meta.env.VITE_APP_NAME}
                            {negocio && <small className="app-sidebar__business">{negocio.nombre}</small>}
                        </span>
                    </Link>
                    <button type="button" className={`btn-close btn-close-white mt-2 me-2 ${flotante ? '' : 'd-lg-none'}`} onClick={onCerrar} aria-label="Cerrar menú" />
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
                                onClick={() => alternarGrupo('catalogo')}
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
                                onClick={() => alternarGrupo('administracion')}
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



