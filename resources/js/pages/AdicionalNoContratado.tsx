import { Link } from 'react-router-dom';
import { Icon } from '@/components/ui';
import { useSesion } from '@/lib/auth';
import { useTitulo } from '@/lib/useTitulo';

/** Se muestra al entrar a una funcionalidad adicional que el negocio no tiene activa. */
export default function AdicionalNoContratado({ etiqueta }: { etiqueta: string }) {
    const { permisos } = useSesion();
    useTitulo(etiqueta+' no contratado');

    return (
        <div className="text-center py-5" role="status">
            <Icon name="lock" className="fs-1 text-body-secondary d-block mb-3" />
            <h1 className="h4">{etiqueta} no está contratado</h1>
            {permisos.includes('gestionar_negocio') ? (
                <>
                    <p>Este adicional se cobra aparte. Actívalo desde Mi plan para volver a usarlo; tus datos se conservan.</p>
                    <Link to="/plan" className="btn btn-primary">Ir a Mi plan</Link>
                </>
            ) : (
                <>
                    <p>Este adicional no está activo en tu negocio. Consulta con el administrador para contratarlo.</p>
                    <Link to="/dashboard" className="btn btn-primary">Volver al inicio</Link>
                </>
            )}
        </div>
    );
}
