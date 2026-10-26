import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import Spinner from 'react-bootstrap/Spinner';
import { Alert, Button } from 'react-bootstrap';
import AppLayout from '@/layouts/AppLayout';
import GuestLayout from '@/layouts/GuestLayout';
import { useAuth, useSesion } from '@/lib/auth';
import Login from '@/pages/Auth/Login';
import SeleccionarSucursal from '@/pages/Auth/SeleccionarSucursal';
import OlvideContrasena from '@/pages/Auth/OlvideContrasena';
import RestablecerContrasena from '@/pages/Auth/RestablecerContrasena';
import Dashboard from '@/pages/Dashboard';
import NotFoundPage from '@/pages/NotFoundPage';
import AdicionalNoContratado from '@/pages/AdicionalNoContratado';
import SinAcceso from '@/pages/SinAcceso';
import UsuariosIndex from '@/pages/Usuarios/Index';
import NuevoUsuario from '@/pages/Usuarios/Nuevo';
import EditarUsuario from '@/pages/Usuarios/Editar';
import SucursalesIndex from '@/pages/Sucursales/Index';
import SucursalFormulario from '@/pages/Sucursales/Formulario';
import EditarSucursal from '@/pages/Sucursales/Editar';
import CajasIndex from '@/pages/Cajas/Index';
import CajaFormulario from '@/pages/Cajas/Formulario';
import EditarCaja from '@/pages/Cajas/Editar';
import NegocioIndex from '@/pages/Negocio/Index';
import PlanIndex from '@/pages/Plan/Index';
import MarcasIndex from '@/pages/Marcas/Index';
import MarcaFormulario from '@/pages/Marcas/Formulario';
import EditarMarca from '@/pages/Marcas/Editar';
import CategoriasIndex from '@/pages/Categorias/Index';
import CategoriaFormulario from '@/pages/Categorias/Formulario';
import EditarCategoria from '@/pages/Categorias/Editar';
import ModificadoresIndex from '@/pages/Modificadores/Index';
import NuevoGrupoModificador from '@/pages/Modificadores/Nuevo';
import EditarGrupoModificador from '@/pages/Modificadores/Editar';
import ProductosIndex from '@/pages/Productos/Index';
import NuevoProducto from '@/pages/Productos/Nuevo';
import EditarProducto from '@/pages/Productos/Editar';
import VentasLayout from '@/layouts/VentasLayout';
import CajaIndex from '@/pages/Caja/Index';
import PosIndex from '@/pages/Pos/Index';
import VentasIndex from '@/pages/Ventas/Index';
import VentaDetalle from '@/pages/Ventas/Detalle';
import ComprobantePublico from '@/pages/Ventas/ComprobantePublico';
import TurnosIndex from '@/pages/Turnos/Index';
import TurnoDetalle from '@/pages/Turnos/Detalle';
import InventarioIndex from '@/pages/Inventario/Index';
import MovimientoFormulario from '@/pages/Inventario/Movimiento';
import HistorialMovimientos from '@/pages/Inventario/Historial';
import MinimoFormulario from '@/pages/Inventario/Minimo';

function Cargando() {
    const { errorSesion, refresh } = useAuth();
    if (errorSesion) {
        return <div className="p-4"><Alert variant="danger">No se pudo cargar tu sesión. {errorSesion}</Alert><Button onClick={() => void refresh().catch(() => {})}>Reintentar</Button></div>;
    }
    return (
        <div className="d-flex justify-content-center py-5">
            <Spinner animation="border" role="status">
                <span className="visually-hidden">Cargando…</span>
            </Spinner>
        </div>
    );
}

/**
 * Rutas que requieren sesión: sin ella, se va al login recordando a dónde quería ir.
 */
function RequiereSesion() {
    const { sesion } = useAuth();
    const location = useLocation();

    if (sesion === undefined) {
        return <Cargando />;
    }

    if (sesion === null) {
        return <Navigate to="/login" replace state={{ desde: location.pathname + location.search }} />;
    }

    return <Outlet />;
}

function RequierePermiso({ permiso }: { permiso: string }) {
    const { permisos } = useSesion();

    return permisos.includes(permiso) ? <Outlet /> : <SinAcceso />;
}

/** Rutas de un adicional: sin él activo (prueba o contratado) se explica cómo contratarlo. */
function RequiereAdicional({ adicional, etiqueta }: { adicional: string; etiqueta: string }) {
    const { contexto } = useSesion();

    return contexto.suscripcion?.adicionales_activos.includes(adicional) ? <Outlet /> : <AdicionalNoContratado etiqueta={etiqueta} />;
}

/** Rutas de un módulo del plan: si el negocio no lo tiene activo, la página no existe para él. */
function RequiereModulo({ modulo }: { modulo: string }) {
    const { contexto } = useSesion();

    return contexto.modulos.includes(modulo) ? <Outlet /> : <NotFoundPage />;
}

function RequiereSucursal() {
    const { contexto } = useSesion();
    const location = useLocation();

    return contexto.sucursal ? <Outlet key={contexto.sucursal.id} />
        : <Navigate to="/seleccionar-sucursal" replace state={{ desde: location.pathname + location.search }} />;
}

/**
 * Rutas solo para invitados (login, recuperar contraseña): con sesión, al inicio.
 */
function SoloInvitados() {
    const { sesion } = useAuth();

    if (sesion === undefined) {
        return <Cargando />;
    }

    if (sesion) {
        return <Navigate to="/dashboard" replace />;
    }

    return <Outlet />;
}

export default function App() {
    return (
        <Routes>
            {/* Enlace que se comparte con el cliente: no pide sesión, la firma del enlace basta. */}
            <Route path="comprobante/:ventaId" element={<ComprobantePublico />} />

            <Route element={<SoloInvitados />}>
                <Route element={<GuestLayout />}>
                    <Route path="login" element={<Login />} />
                    <Route path="olvide-contrasena" element={<OlvideContrasena />} />
                    <Route path="restablecer-contrasena/:token" element={<RestablecerContrasena />} />
                </Route>
            </Route>

            <Route element={<RequiereSesion />}>
                <Route element={<GuestLayout />}>
                    <Route path="seleccionar-sucursal" element={<SeleccionarSucursal />} />
                </Route>
                <Route element={<RequiereSucursal />}>
                    <Route element={<VentasLayout />}>
                        <Route element={<RequierePermiso permiso="vender" />}>
                            <Route path="pos" element={<PosIndex />} />
                            <Route path="ventas" element={<VentasIndex />} />
                            <Route path="ventas/:ventaId" element={<VentaDetalle />} />
                        </Route>
                        <Route element={<RequierePermiso permiso="operar_caja" />}>
                            <Route path="caja" element={<CajaIndex />} />
                            <Route path="turnos" element={<TurnosIndex />} />
                            <Route path="turnos/:turnoId" element={<TurnoDetalle />} />
                        </Route>
                    </Route>
                    <Route element={<AppLayout />}>
                        <Route index element={<Navigate to="/dashboard" replace />} />
                        <Route path="dashboard" element={<Dashboard />} />
                        <Route element={<RequierePermiso permiso="gestionar_negocio" />}>
                            <Route path="negocio" element={<NegocioIndex />} />
                            <Route path="plan" element={<PlanIndex />} />
                        </Route>
                        <Route element={<RequierePermiso permiso="gestionar_cajas" />}>
                            <Route path="cajas" element={<CajasIndex />}>
                                <Route path="nueva" element={<CajaFormulario />} />
                                <Route path=":cajaId/editar" element={<EditarCaja />} />
                            </Route>
                        </Route>
                        <Route element={<RequierePermiso permiso="gestionar_usuarios" />}>
                            <Route path="usuarios" element={<UsuariosIndex />}>
                                <Route path="nuevo" element={<NuevoUsuario />} />
                                <Route path=":usuarioId/editar" element={<EditarUsuario />} />
                            </Route>
                        </Route>
                        <Route element={<RequierePermiso permiso="gestionar_catalogo" />}>
                            <Route path="marcas" element={<MarcasIndex />}>
                                <Route path="nueva" element={<MarcaFormulario />} />
                                <Route path=":marcaId/editar" element={<EditarMarca />} />
                            </Route>
                            <Route path="categorias" element={<CategoriasIndex />}>
                                <Route path="nueva" element={<CategoriaFormulario />} />
                                <Route path=":categoriaId/editar" element={<EditarCategoria />} />
                            </Route>
                            <Route element={<RequiereModulo modulo="modificadores" />}>
                                <Route path="modificadores" element={<ModificadoresIndex />}>
                                    <Route path="nuevo" element={<NuevoGrupoModificador />} />
                                    <Route path=":grupoId/editar" element={<EditarGrupoModificador />} />
                                </Route>
                            </Route>
                            <Route path="productos" element={<ProductosIndex />}>
                                <Route path="nuevo" element={<NuevoProducto />} />
                                <Route path=":productoId/editar" element={<EditarProducto />} />
                            </Route>
                        </Route>
                        <Route element={<RequierePermiso permiso="gestionar_inventario" />}>
                            <Route element={<RequiereAdicional adicional="inventario" etiqueta="Inventario" />}>
                                <Route path="inventario" element={<InventarioIndex />}>
                                    <Route path=":productoId/movimiento" element={<MovimientoFormulario />} />
                                    <Route path=":productoId/historial" element={<HistorialMovimientos />} />
                                    <Route path=":productoId/minimo" element={<MinimoFormulario />} />
                                </Route>
                            </Route>
                        </Route>
                        <Route element={<RequierePermiso permiso="gestionar_sucursales" />}>
                            <Route path="sucursales" element={<SucursalesIndex />}>
                                <Route path="nueva" element={<SucursalFormulario />} />
                                <Route path=":sucursalId/editar" element={<EditarSucursal />} />
                            </Route>
                        </Route>
                        <Route path="*" element={<NotFoundPage />} />
                    </Route>
                </Route>
            </Route>
        </Routes>
    );
}



