import { Button, Dropdown } from 'react-bootstrap';
import { Icon, SelectorSucursal } from '@/components/ui';
import { useAuth, useSesion } from '@/lib/auth';
import { mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';

interface BarraSuperiorProps {
    onAbrirMenu: () => void;
}

/**
 * Barra superior: botón de menú (en pantallas chicas), sucursal activa,
 * código del negocio y usuario.
 */
export default function BarraSuperior({ onAbrirMenu }: BarraSuperiorProps) {
    const { user, contexto } = useSesion();
    const { logout } = useAuth();

    return (
        <header className="app-topbar">
            <Button variant="light" className="d-lg-none" onClick={onAbrirMenu} aria-label="Abrir menú">
                <Icon name="list" />
            </Button>

            <div className="min-w-0">
                <SelectorSucursal />
            </div>

            {contexto.negocio && (
                <span className="codigo-negocio d-none d-md-inline-flex" title="Código de negocio: tus usuarios lo usan para ingresar">
                    <Icon name="building" />
                    Código <strong>{contexto.negocio.codigo}</strong>
                </span>
            )}

            <div className="ms-auto d-flex align-items-center gap-2">
                <Dropdown align="end">
                    <Dropdown.Toggle variant="light" className="d-flex align-items-center gap-2">
                        <Icon name="person-circle" />
                        <span className="d-none d-sm-inline">{user.name}</span>
                    </Dropdown.Toggle>
                    <Dropdown.Menu>
                        <Dropdown.Header>@{user.username}</Dropdown.Header>
                        <Dropdown.Divider />
                        <Dropdown.Item as="button" onClick={() => void logout().catch((error: unknown) => notificar(`No se pudo cerrar la sesión. ${mensajeDeError(error)}`, 'error'))}>
                            <Icon name="box-arrow-right" className="me-2" />
                            Cerrar sesión
                        </Dropdown.Item>
                    </Dropdown.Menu>
                </Dropdown>
            </div>
        </header>
    );
}
