import axios from 'axios';
import { Button, Spinner } from 'react-bootstrap';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { FormDialog } from '@/components/ui';
import { mensajeDeError } from '@/lib/http';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Usuario } from '@/types/user';
import UsuarioFormulario from './Formulario';

export default function EditarUsuario() {
    const { usuarioId } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const consulta = useConsultaApi<{ data: Usuario }>(`/usuarios/${usuarioId}`);
    const cerrar = () => navigate(`/usuarios${location.search}`, { replace: true });
    const noDisponible = axios.isAxiosError(consulta.error) && consulta.error.response?.status === 404;

    if (consulta.datos) {
        return <UsuarioFormulario key={consulta.datos.data.id} usuario={consulta.datos.data} />;
    }

    return (
        <FormDialog title="Editar usuario" onHide={cerrar} actions={<Button variant="outline-secondary" onClick={cerrar}>Volver al listado</Button>}>
            {consulta.cargando ? (
                <p role="status"><Spinner as="span" animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando usuario…</p>
            ) : (
                <div role="alert">
                    <p>{noDisponible ? 'El usuario no existe o ya no está disponible en este negocio.' : `No se pudo cargar el usuario. ${mensajeDeError(consulta.error)}`}</p>
                    {!noDisponible && <Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button>}
                </div>
            )}
        </FormDialog>
    );
}
