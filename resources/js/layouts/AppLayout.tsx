import { Outlet, NavLink } from 'react-router-dom';
import Container from 'react-bootstrap/Container';
import Nav from 'react-bootstrap/Nav';
import Navbar from 'react-bootstrap/Navbar';
import NavDropdown from 'react-bootstrap/NavDropdown';
import type { User } from '@/types/user';

interface Props {
    user: User;
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function AppLayout({ user }: Props) {
    return (
        <>
            <Navbar expand="md" bg="white" className="shadow-sm" collapseOnSelect>
                <Container>
                    <Navbar.Brand as={NavLink} to="/">
                        {import.meta.env.VITE_APP_NAME}
                    </Navbar.Brand>
                    <Navbar.Toggle aria-controls="main-nav" />
                    <Navbar.Collapse id="main-nav">
                        <Nav className="me-auto">
                            <Nav.Link as={NavLink} to="/" end eventKey="home">
                                Inicio
                            </Nav.Link>
                        </Nav>
                        <Nav>
                            <NavDropdown title={user.name} align="end">
                                <form method="POST" action="/logout">
                                    <input type="hidden" name="_token" value={csrfToken()} />
                                    <button type="submit" className="dropdown-item">
                                        Cerrar sesión
                                    </button>
                                </form>
                            </NavDropdown>
                        </Nav>
                    </Navbar.Collapse>
                </Container>
            </Navbar>
            <main className="py-4">
                <Container>
                    <Outlet />
                </Container>
            </main>
        </>
    );
}
