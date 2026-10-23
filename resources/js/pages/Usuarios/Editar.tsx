import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { EditDialog } from '@/components/ui';
import type { Usuario } from '@/types/user';
import UsuarioFormulario from './Formulario';

export default function EditarUsuario() {
    const { usuarioId } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const cerrar = () => navigate(`/usuarios${location.search}`, { replace: true });

    return (
        <EditDialog<Usuario> route={`/usuarios/${usuarioId}`} title="Editar usuario" onHide={cerrar}
            loadingLabel="Cargando usuario…" unavailableMessage="El usuario no existe o ya no está disponible en este negocio."
            errorMessage="No se pudo cargar el usuario.">
            {(usuario) => <UsuarioFormulario key={usuario.id} usuario={usuario} />}
        </EditDialog>
    );
}
