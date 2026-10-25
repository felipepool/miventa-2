import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Alert } from 'react-bootstrap';
import { useAuth } from '@/lib/auth';

/**
 * Aviso de prueba gratuita o de solo lectura. Al vencer la prueba vuelve a
 * consultar la sesión para que la pantalla refleje el nuevo estado sin recargar.
 */
export default function AvisoSuscripcion() {
    const { sesion, refresh } = useAuth();
    const estado = sesion?.contexto.suscripcion;

    useEffect(() => {
        if (!estado?.en_prueba || !estado.prueba_hasta) { return; }
        const retraso = Math.min(2147483647, Math.max(1000, new Date(estado.prueba_hasta).getTime() - Date.now() + 100));
        const timer = window.setTimeout(() => { void refresh().catch(() => {}); }, retraso);
        return () => window.clearTimeout(timer);
    }, [estado?.en_prueba, estado?.prueba_hasta, refresh]);

    if (!estado || (!estado.en_prueba && !estado.solo_lectura)) {
        return null;
    }

    return (
        <Alert variant={estado.solo_lectura ? 'warning' : 'info'}>
            {estado.solo_lectura ? 'Tu prueba terminó. Puedes consultar tus datos, pero no vender ni modificar información operativa.'
                : `Prueba gratuita: quedan ${estado.dias_restantes} días. Los adicionales están habilitados durante la prueba.`}
            {sesion?.permisos.includes('gestionar_negocio') && <> <Link to="/plan">{estado.contratado ? 'Ver contratación' : 'Elegir plan y adicionales'}</Link>.</>}
        </Alert>
    );
}
