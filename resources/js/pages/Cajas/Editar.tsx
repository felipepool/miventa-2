import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { EditDialog } from '@/components/ui';
import type { Caja } from '@/types/caja';
import CajaFormulario from './Formulario';

export default function EditarCaja() {
    const { cajaId } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const cerrar = () => navigate('/cajas'+location.search, { replace: true });
    return <EditDialog<Caja> route={'/cajas/'+cajaId} title="Editar caja" size="md" onHide={cerrar}
        loadingLabel="Cargando caja…" unavailableMessage="La caja no existe o ya no está disponible en este negocio."
        errorMessage="No se pudo cargar la caja.">
        {(caja) => <CajaFormulario key={caja.id} caja={caja} />}
    </EditDialog>;
}
