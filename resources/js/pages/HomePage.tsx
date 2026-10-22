import Card from 'react-bootstrap/Card';
import type { User } from '@/types/user';

interface Props {
    user: User;
}

export default function HomePage({ user }: Props) {
    return (
        <Card>
            <Card.Body>
                <Card.Title as="h1" className="h4">
                    Hola, {user.name}
                </Card.Title>
                <Card.Text className="text-body-secondary mb-0">
                    Bienvenido a MiVenta.
                </Card.Text>
            </Card.Body>
        </Card>
    );
}
