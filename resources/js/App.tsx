import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import Spinner from 'react-bootstrap/Spinner';
import AppLayout from '@/layouts/AppLayout';
import GuestLayout from '@/layouts/GuestLayout';
import { useAuth } from '@/lib/auth';
import Login from '@/pages/Auth/Login';
import OlvideContrasena from '@/pages/Auth/OlvideContrasena';
import RestablecerContrasena from '@/pages/Auth/RestablecerContrasena';
import Dashboard from '@/pages/Dashboard';
import NotFoundPage from '@/pages/NotFoundPage';

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
    const { user } = useAuth();
    const location = useLocation();

    if (user === undefined) {
        return <Cargando />;
    }

    if (user === null) {
        return <Navigate to="/login" replace state={{ desde: location.pathname + location.search }} />;
    }

    return <Outlet />;
}

/**
 * Rutas solo para invitados (login, recuperar contraseña): con sesión, al inicio.
 */
function SoloInvitados() {
    const { user } = useAuth();

    if (user === undefined) {
        return <Cargando />;
    }

    if (user) {
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
                    <Route path="*" element={<NotFoundPage />} />
                </Route>
            </Route>
        </Routes>
    );
}
