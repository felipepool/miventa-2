import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import Spinner from 'react-bootstrap/Spinner';
import AppLayout from '@/layouts/AppLayout';
import GuestLayout from '@/layouts/GuestLayout';
import { useAuth, useSesion } from '@/lib/auth';
import Login from '@/pages/Auth/Login';
import OlvideContrasena from '@/pages/Auth/OlvideContrasena';
import RestablecerContrasena from '@/pages/Auth/RestablecerContrasena';
import Dashboard from '@/pages/Dashboard';
import NotFoundPage from '@/pages/NotFoundPage';
import SinAcceso from '@/pages/SinAcceso';
import UsuariosIndex from '@/pages/Usuarios/Index';
import NuevoUsuario from '@/pages/Usuarios/Nuevo';
import EditarUsuario from '@/pages/Usuarios/Editar';
import SucursalesIndex from '@/pages/Sucursales/Index';
import SucursalFormulario from '@/pages/Sucursales/Formulario';
import EditarSucursal from '@/pages/Sucursales/Editar';

function Cargando() {
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
            <Route element={<SoloInvitados />}>
                <Route element={<GuestLayout />}>
                    <Route path="login" element={<Login />} />
                    <Route path="olvide-contrasena" element={<OlvideContrasena />} />
                    <Route path="restablecer-contrasena/:token" element={<RestablecerContrasena />} />
                </Route>
            </Route>

            <Route element={<RequiereSesion />}>
                <Route element={<AppLayout />}>
                    <Route index element={<Navigate to="/dashboard" replace />} />
                    <Route path="dashboard" element={<Dashboard />} />
                    <Route element={<RequierePermiso permiso="gestionar_usuarios" />}>
                        <Route path="usuarios" element={<UsuariosIndex />}>
                            <Route path="nuevo" element={<NuevoUsuario />} />
                            <Route path=":usuarioId/editar" element={<EditarUsuario />} />
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
        </Routes>
    );
}
