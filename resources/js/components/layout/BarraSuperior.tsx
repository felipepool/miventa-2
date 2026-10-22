import { Button, Dropdown } from 'react-bootstrap';
import { Icon } from '@/components/ui';
import { useAuth, useUser } from '@/lib/auth';

interface BarraSuperiorProps {
    onAbrirMenu: () => void;
}

/**
 * Barra superior: botón de menú (en pantallas chicas) y usuario.
 */
export default function BarraSuperior({ onAbrirMenu }: BarraSuperiorProps) {
    const user = useUser();
    const { logout } = useAuth();

    return (
        <header className="app-topbar">
            <Button variant="light" className="d-lg-none" onClick={onAbrirMenu} aria-label="Abrir menú">
                <Icon name="list" />
            </Button>

            <div className="ms-auto d-flex align-items-center gap-2">
                <Dropdown align="end">
                    <Dropdown.Toggle variant="light" className="d-flex align-items-center gap-2">
                        <Icon name="person-circle" />
                        <span className="d-none d-sm-inline">{user.name}</span>
                    </Dropdown.Toggle>
                    <Dropdown.Menu>
                        <Dropdown.Header>{user.email}</Dropdown.Header>
                        <Dropdown.Divider />
                        <Dropdown.Item as="button" onClick={() => void logout()}>
                            <Icon name="box-arrow-right" className="me-2" />
                            Cerrar sesión
                        </Dropdown.Item>
                    </Dropdown.Menu>
                </Dropdown>
            </div>
        </header>
    );
}
