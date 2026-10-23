import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { EditDialog } from '@/components/ui';
import type { Sucursal } from '@/types/sucursal';
import SucursalFormulario from './Formulario';

export default function EditarSucursal() {
    const { sucursalId } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const cerrar = () => navigate(`/sucursales${location.search}`, { replace: true });

    return (
        <EditDialog<Sucursal> route={`/sucursales/${sucursalId}`} title="Editar sucursal" size="md" onHide={cerrar}
            loadingLabel="Cargando sucursal…" unavailableMessage="La sucursal no existe o ya no está disponible en este negocio."
            errorMessage="No se pudo cargar la sucursal.">
            {(sucursal) => <SucursalFormulario key={sucursal.id} sucursal={sucursal} />}
        </EditDialog>
    );
}
