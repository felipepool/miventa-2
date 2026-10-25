import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { EditDialog } from '@/components/ui';
import type { ElementoCatalogo } from '@/types/catalogo';
import CategoriaFormulario from './Formulario';

export default function EditarCategoria() {
    const { categoriaId } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const cerrar = () => navigate('/categorias'+location.search, { replace: true });
    return <EditDialog<ElementoCatalogo> route={'/categorias/'+categoriaId} title="Editar categoría" size="md" onHide={cerrar}
        loadingLabel="Cargando categoría…" unavailableMessage="La categoría no existe o ya no está disponible en este negocio."
        errorMessage="No se pudo cargar la categoría.">
        {(categoria) => <CategoriaFormulario key={categoria.id} categoria={categoria} />}
    </EditDialog>;
}
