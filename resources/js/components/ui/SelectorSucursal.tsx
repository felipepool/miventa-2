import { useState } from 'react';
import { Dropdown } from 'react-bootstrap';
import Icon from '@/components/ui/Icon';
import { useAuth, useSesion } from '@/lib/auth';
import { mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';

/**
 * Muestra la sucursal activa y permite cambiarla.
 * Con una sola sucursal disponible se muestra solo su nombre.
 */
export default function SelectorSucursal() {
    const { sucursal, sucursales } = useSesion().contexto;
    const { cambiarSucursal, refresh } = useAuth();
    const [cambiando, setCambiando] = useState(false);

    if (!sucursal) {
        return (
            <span className="text-muted small d-flex align-items-center gap-2">
                <Icon name="exclamation-circle" />
                Sin sucursal asignada
            </span>
        );
    }

    if (sucursales.length <= 1) {
        return (
            <span className="d-flex align-items-center gap-2 fw-semibold text-truncate">
                <Icon name="geo-alt" className="text-primary" />
                <span className="text-truncate">{sucursal.nombre}</span>
            </span>
        );
    }

    const cambiar = async (sucursalId: string) => {
        if (sucursalId === sucursal.id) {
            return;
        }

        setCambiando(true);

        try {
            await cambiarSucursal(sucursalId);
        } catch (error) {
            notificar(`No se pudo cambiar la sucursal. ${mensajeDeError(error)}`, 'error');
            await refresh().catch(() => {});
        } finally {
            setCambiando(false);
        }
    };

    return (
        <Dropdown>
            <Dropdown.Toggle
                variant="light"
                className="d-flex align-items-center gap-2 fw-semibold mw-100"
                aria-label="Cambiar sucursal"
                disabled={cambiando}
            >
                <Icon name="geo-alt" className="text-primary" />
                <span className="text-truncate">{sucursal.nombre}</span>
            </Dropdown.Toggle>
            <Dropdown.Menu>
                <Dropdown.Header>Cambiar sucursal</Dropdown.Header>
                {sucursales.map((opcion) => (
                    <Dropdown.Item
                        key={opcion.id}
                        as="button"
                        active={opcion.id === sucursal.id}
                        disabled={cambiando}
                        onClick={() => void cambiar(opcion.id)}
                    >
                        {opcion.nombre}
                    </Dropdown.Item>
                ))}
            </Dropdown.Menu>
        </Dropdown>
    );
}
