import { Link } from 'react-router-dom';
import { Icon } from '@/components/ui';
import { useTitulo } from '@/lib/useTitulo';

export default function SinAcceso() {
    useTitulo('Sin acceso');

    return (
        <div className="text-center py-5">
            <Icon name="shield-lock" className="fs-1 text-body-secondary d-block mb-3" />
            <h1 className="h4">Sin acceso</h1>
            <p>No tienes permiso para consultar esta página.</p>
            <Link to="/dashboard" className="btn btn-primary">Volver al inicio</Link>
        </div>
    );
}
