import axios from 'axios';
import type { ReactNode } from 'react';
import { Button, Spinner } from 'react-bootstrap';
import { mensajeDeError } from '@/lib/http';
import { useConsultaApi } from '@/lib/useConsultaApi';
import FormDialog from './FormDialog';

interface EditDialogProps<T> {
    route: string;
    title: string;
    loadingLabel: string;
    unavailableMessage: string;
    errorMessage: string;
    onHide: () => void;
    size?: 'md' | 'lg' | 'xl';
    children: (record: T) => ReactNode;
}

/** Flujo común de edición: diálogo de carga y luego formulario con sus datos completos. */
export default function EditDialog<T>({ route, title, loadingLabel, unavailableMessage, errorMessage, onHide, size, children }: EditDialogProps<T>) {
    const consulta = useConsultaApi<{ data: T }>(route);
    const noDisponible = axios.isAxiosError(consulta.error) && consulta.error.response?.status === 404;

    if (consulta.datos) {
        return children(consulta.datos.data);
    }

    return (
        <FormDialog title={title} size={size} onHide={onHide} actions={<Button variant="outline-secondary" onClick={onHide}>Volver al listado</Button>}>
            {consulta.cargando ? (
                <p role="status"><Spinner as="span" animation="border" size="sm" className="me-2" aria-hidden="true" />{loadingLabel}</p>
            ) : (
                <div role="alert">
                    <p>{noDisponible ? unavailableMessage : `${errorMessage} ${mensajeDeError(consulta.error)}`}</p>
                    {!noDisponible && <Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button>}
                </div>
            )}
        </FormDialog>
    );
}
