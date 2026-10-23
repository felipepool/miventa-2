import Card from 'react-bootstrap/Card';
import { Link } from 'react-router-dom';
import { Icon, PageHeader } from '@/components/ui';
import { useSesion } from '@/lib/auth';

export default function Dashboard() {
    const { user, contexto, permisos } = useSesion();

    return (
        <>
            <PageHeader title="Inicio" subtitle={`Hola, ${user.name}. ${contexto.negocio ? `Estás en ${contexto.negocio.nombre}.` : 'Bienvenido a MiVenta.'}`} />
            {permisos.includes('gestionar_usuarios') && (
                <Card as="section" aria-labelledby="inicio-equipo">
                    <Card.Body>
                        <h2 id="inicio-equipo" className="h5"><Icon name="people" className="me-2" />Tu equipo</h2>
                        <p className="text-body-secondary">Agrega a las personas que trabajan contigo y define a qué sucursales y funciones pueden acceder.</p>
                        <div className="d-flex flex-wrap gap-2">
                            <Link to="/usuarios/nuevo" className="btn btn-primary"><Icon name="plus-lg" className="me-2" />Nuevo usuario</Link>
                            <Link to="/usuarios" className="btn btn-outline-secondary">Ver usuarios</Link>
                        </div>
                    </Card.Body>
                </Card>
            )}
        </>
    );
}
