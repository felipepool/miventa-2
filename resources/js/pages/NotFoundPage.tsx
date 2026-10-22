import { Link } from 'react-router-dom';
import { useTitulo } from '@/lib/useTitulo';

export default function NotFoundPage() {
    useTitulo('Página no encontrada');

    return (
        <div className="text-center py-5">
            <h1 className="h4">Página no encontrada</h1>
            <Link to="/dashboard">Volver al inicio</Link>
        </div>
    );
}
