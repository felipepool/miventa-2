import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { EditDialog } from '@/components/ui';
import type { GrupoModificador } from '@/types/catalogo';
import GrupoFormulario from './Formulario';

export default function EditarGrupoModificador() {
    const { grupoId } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const cerrar = () => navigate('/modificadores'+location.search, { replace: true });
    return <EditDialog<GrupoModificador> route={'/grupos-modificadores/'+grupoId} title="Editar grupo" size="lg" onHide={cerrar}
        loadingLabel="Cargando grupo…" unavailableMessage="El grupo no existe o ya no está disponible en este negocio."
        errorMessage="No se pudo cargar el grupo.">
        {(grupo) => <GrupoFormulario key={grupo.id} grupo={grupo} />}
    </EditDialog>;
}
