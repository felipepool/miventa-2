import Card from 'react-bootstrap/Card';
import { useUser } from '@/lib/auth';
import { useTitulo } from '@/lib/useTitulo';

export default function Dashboard() {
    useTitulo('Inicio');

    const user = useUser();

    return (
        <Card>
            <Card.Body>
                <Card.Title as="h1" className="h4">
                    Hola, {user.name}
                </Card.Title>
                <Card.Text className="text-body-secondary mb-0">Bienvenido a MiVenta.</Card.Text>
            </Card.Body>
        </Card>
    );
}
