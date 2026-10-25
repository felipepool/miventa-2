import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { EditDialog } from '@/components/ui';
import type { Producto } from '@/types/catalogo';
import ProductoFormulario from './Formulario';

export default function EditarProducto() {
    const { productoId } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const cerrar = () => navigate('/productos'+location.search, { replace: true });
    return <EditDialog<Producto> route={'/productos/'+productoId} title="Editar producto" size="lg" onHide={cerrar}
        loadingLabel="Cargando producto…" unavailableMessage="El producto no existe o ya no está disponible en este negocio."
        errorMessage="No se pudo cargar el producto.">
        {(producto) => <ProductoFormulario key={producto.id} producto={producto} />}
    </EditDialog>;
}
