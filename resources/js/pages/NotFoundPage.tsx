import { Link } from 'react-router-dom';

export default function NotFoundPage() {
    return (
        <div className="text-center py-5">
            <h1 className="h4">Página no encontrada</h1>
            <Link to="/">Volver al inicio</Link>
        </div>
    );
}
