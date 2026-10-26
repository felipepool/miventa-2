import { useState } from 'react';
import { Button, Spinner, Table } from 'react-bootstrap';
import { FormDialog } from '@/components/ui';
import Pagination from '@/components/ui/Pagination';
import { mensajeDeError } from '@/lib/http';
import { formatearCantidad } from '@/lib/inventario';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Paginated } from '@/types/api';
import type { ItemInventario, MovimientoStock, TipoMovimientoHistorial } from '@/types/inventario';
import DialogoProducto from './DialogoProducto';

const ETIQUETAS: Record<TipoMovimientoHistorial, string> = { entrada: 'Entrada', salida: 'Salida', ajuste: 'Ajuste', venta: 'Venta', anulacion: 'Venta anulada', devolucion: 'Devolución' };

export default function Historial() {
    return <DialogoProducto title="Historial de movimientos" size="lg">
        {(item, cerrar) => <Contenido key={item.id} item={item} cerrar={cerrar} />}
    </DialogoProducto>;
}

function Contenido({ item, cerrar }: { item: ItemInventario; cerrar: () => void }) {
    const [pagina, setPagina] = useState(1);
    const movimientos = useConsultaApi<Paginated<MovimientoStock>>('/inventario/'+item.id+'/movimientos?page='+pagina);
    const filas = movimientos.datos?.data ?? [];

    return <FormDialog title="Historial de movimientos" size="lg" onHide={cerrar} actions={<Button variant="outline-secondary" onClick={cerrar}>Cerrar</Button>}>
        <p className="mb-3"><strong>{item.nombre}</strong><small className="d-block text-body-secondary">Stock actual: {formatearCantidad(item.cantidad, item.unidad_venta)}</small></p>
        {movimientos.cargando && <p role="status"><Spinner as="span" animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando movimientos…</p>}
        {Boolean(movimientos.error) && <div role="alert"><p>No se pudo cargar el historial. {mensajeDeError(movimientos.error)}</p><Button variant="outline-secondary" onClick={movimientos.recargar}>Reintentar</Button></div>}
        {movimientos.datos && (filas.length === 0 ? <p role="status">Este producto aún no tiene movimientos en esta sucursal.</p> : <>
            <Table responsive size="sm" className="align-middle">
                <caption className="visually-hidden">Movimientos de {item.nombre}</caption>
                <thead><tr><th scope="col">Fecha</th><th scope="col">Tipo</th><th scope="col">Cantidad</th><th scope="col">Stock</th><th scope="col">Motivo</th><th scope="col">Usuario</th></tr></thead>
                <tbody>{filas.map((movimiento) => <tr key={movimiento.id}>
                    <td>{new Date(movimiento.created_at).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' })}</td>
                    <td>{ETIQUETAS[movimiento.tipo]}</td>
                    <td>{Number(movimiento.cantidad) > 0 ? '+' : ''}{formatearCantidad(movimiento.cantidad, item.unidad_venta)}</td>
                    <td>{formatearCantidad(movimiento.stock_resultante, item.unidad_venta)}</td>
                    <td>{movimiento.motivo ?? '—'}</td>
                    <td>{movimiento.usuario ?? '—'}</td>
                </tr>)}</tbody>
            </Table>
            {movimientos.datos.meta.last_page > 1 && <Pagination meta={movimientos.datos.meta} onPageChange={setPagina} />}
        </>)}
    </FormDialog>;
}

