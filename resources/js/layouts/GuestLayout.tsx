import { Card } from 'react-bootstrap';
import { Outlet } from 'react-router-dom';

/**
 * Estructura de las páginas públicas (login, recuperar contraseña, etc.).
 */
export default function GuestLayout() {
    return (
        <div className="auth-wrapper">
            <div className="auth-card">
                <Card className="border-0 shadow">
                    <Card.Body className="p-4">
                        <Outlet />
                    </Card.Body>
                </Card>
            </div>
        </div>
    );
}
