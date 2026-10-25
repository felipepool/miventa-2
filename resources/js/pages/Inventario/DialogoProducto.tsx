import { Button, Spinner } from 'react-bootstrap';
import type { ReactNode } from 'react';
import { useLocation, useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { FormDialog } from '@/components/ui';
import { mensajeDeError } from '@/lib/http';
import type { ItemInventario } from '@/types/inventario';
import type { InventarioContexto } from './Index';

interface DialogoProductoProps {
    title: string;
    size?: 'md' | 'lg';
    children: (item: ItemInventario, cerrar: () => void, contexto: InventarioContexto) => ReactNode;
}

/**
 * Busca el producto de la ruta en el listado ya cargado y muestra el estado de carga,
 * error o producto no disponible con el mismo diálogo que usan los formularios.
 */
export default function DialogoProducto({ title, size = 'md', children }: DialogoProductoProps) {
    const { productoId } = useParams();
    const contexto = useOutletContext<InventarioContexto>();
    const location = useLocation();
    const navigate = useNavigate();
    const cerrar = () => navigate('/inventario'+location.search, { replace: true });
    const item = contexto.items.find((candidato) => candidato.id === productoId);

    if (item) {
        return children(item, cerrar, contexto);
    }

    return (
        <FormDialog title={title} size={size} onHide={cerrar} actions={<Button variant="outline-secondary" onClick={cerrar}>Volver al listado</Button>}>
            {contexto.cargando ? (
                <p role="status"><Spinner as="span" animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando producto…</p>
            ) : contexto.error ? (
                <div role="alert"><p>No se pudo cargar el inventario. {mensajeDeError(contexto.error)}</p><Button variant="outline-secondary" onClick={contexto.recargar}>Reintentar</Button></div>
            ) : (
                <div role="alert"><p>El producto no existe, no controla stock o no está en este listado.</p></div>
            )}
        </FormDialog>
    );
}
