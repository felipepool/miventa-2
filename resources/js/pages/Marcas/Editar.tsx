import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { EditDialog } from '@/components/ui';
import type { ElementoCatalogo } from '@/types/catalogo';
import MarcaFormulario from './Formulario';

export default function EditarMarca() {
    const { marcaId } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const cerrar = () => navigate('/marcas'+location.search, { replace: true });
    return <EditDialog<ElementoCatalogo> route={'/marcas/'+marcaId} title="Editar marca" size="md" onHide={cerrar}
        loadingLabel="Cargando marca…" unavailableMessage="La marca no existe o ya no está disponible en este negocio."
        errorMessage="No se pudo cargar la marca.">
        {(marca) => <MarcaFormulario key={marca.id} marca={marca} />}
    </EditDialog>;
}
