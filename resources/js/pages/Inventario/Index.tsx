import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Badge, Button, Form, Spinner } from 'react-bootstrap';
import { Outlet, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { DataTable, PageHeader, SearchInput, TableActions } from '@/components/ui';
import type { Column } from '@/components/ui';
import { formatearCantidad } from '@/lib/inventario';
import { mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Paginated } from '@/types/api';
import type { ItemInventario, ListadoInventario } from '@/types/inventario';

export interface InventarioContexto {
    items: ItemInventario[];
    cargando: boolean;
    error: unknown;
    recargar: () => void;
    onGuardado: (mensaje: string) => void;
}

export default function InventarioIndex() {
    const location = useLocation();
    const navigate = useNavigate();
    const [parametros, setParametros] = useSearchParams();
    const [reiniciosBusqueda, setReiniciosBusqueda] = useState(0);
    const consulta = parametros.toString();
    const listado = useConsultaApi<Paginated<ItemInventario> & ListadoInventario>('/inventario'+(consulta ? '?'+consulta : ''));
    const buscar = parametros.get('buscar') ?? '';
    const categoria = parametros.get('categoria') ?? '';
    const stock = parametros.get('stock') ?? '';
    const hayFiltros = Boolean(buscar || categoria || stock);
    const meta = listado.datos?.meta;
    const items = listado.datos?.data ?? [];
    const categorias = listado.datos?.categorias ?? [];

    useEffect(() => {
        if (meta && meta.current_page > meta.last_page) {
            setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(meta.last_page)); return nuevos; }, { replace: true });
        }
    }, [meta, setParametros]);

    const filtrar = (campo: string, valor: string) => setParametros((actuales) => {
        const nuevos = new URLSearchParams(actuales);
        if (valor) { nuevos.set(campo, valor); } else { nuevos.delete(campo); }
        nuevos.delete('page');
        return nuevos;
    }, { replace: campo === 'buscar' });
    const limpiarFiltros = () => { setReiniciosBusqueda((actual) => actual + 1); setParametros({}); };

    const columnas: Column<ItemInventario>[] = [
        { header: 'Producto', mobile: 'title', cell: (item) => <><strong>{item.nombre}</strong>{item.sku && <small className="d-block text-body-secondary">SKU: {item.sku}</small>}</> },
        { header: 'Stock', cell: (item) => <>{formatearCantidad(item.cantidad, item.unidad_venta)}{item.stock_bajo && <Badge pill bg="warning-subtle" text="warning-emphasis" className="ms-2">Stock bajo</Badge>}</> },
        { header: 'Mínimo', cell: (item) => Number(item.stock_minimo) > 0 ? formatearCantidad(item.stock_minimo, item.unidad_venta) : '—' },
        { header: 'Acciones', mobile: 'actions', cell: (item) => <TableActions recordLabel={item.nombre}
            primaryAction={{ label: 'Movimiento', icon: 'arrow-left-right', to: '/inventario/'+item.id+'/movimiento'+location.search }}
            actions={[
                { label: 'Ver historial', icon: 'clock-history', onClick: () => navigate('/inventario/'+item.id+'/historial'+location.search) },
                { label: 'Definir mínimo', icon: 'bell', onClick: () => navigate('/inventario/'+item.id+'/minimo'+location.search) },
            ]} /> },
    ];
    let status: ReactNode;
    if (listado.error) {
        status = <div className="p-3" role="alert"><p>No se pudo cargar el inventario. {mensajeDeError(listado.error)}</p><Button variant="outline-secondary" onClick={listado.recargar}>Reintentar</Button></div>;
    } else if (listado.cargando) {
        status = <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando inventario…</div>;
    }

    return <>
        <PageHeader title="Inventario" subtitle="Controla las existencias de la sucursal actual." />
        <DataTable columns={columnas} rows={items} rowKey={(item) => item.id} caption="Inventario de la sucursal"
            status={status} busy={listado.cargando} meta={meta} emptyIcon="boxes"
            onPageChange={(pagina) => setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(pagina)); return nuevos; })}
            emptyMessage={hayFiltros ? 'No encontramos productos con estos filtros.' : 'Aún no hay productos con control de stock. Actívalo al crear o editar un producto.'}
            emptyAction={hayFiltros ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todo el inventario</Button> : undefined}
            toolbar={<div role="search" aria-label="Buscar y filtrar inventario">
                <div className="table-toolbar align-items-end">
                    <div className="table-toolbar__search"><Form.Label htmlFor="inventario-buscar">Buscar producto</Form.Label>
                        <SearchInput id="inventario-buscar" key={reiniciosBusqueda} value={buscar} label="Buscar por nombre, SKU o código de barras" placeholder="Nombre, SKU o código de barras" maxLength={100} onChange={(valor) => filtrar('buscar', valor)} />
                    </div>
                    <div className="table-toolbar__filters"><Form.Group controlId="inventario-categoria"><Form.Label>Categoría</Form.Label>
                        <Form.Select className="w-100" value={categoria} disabled={!listado.datos} onChange={(event) => filtrar('categoria', event.target.value)}>
                            <option value="">Todas</option>{categorias.map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.nombre}{opcion.activa ? '' : ' (inactiva)'}</option>)}
                        </Form.Select>
                    </Form.Group></div>
                    <div className="table-toolbar__filters"><Form.Group controlId="inventario-stock"><Form.Label>Stock</Form.Label>
                        <Form.Select className="w-100" value={stock} onChange={(event) => filtrar('stock', event.target.value)}>
                            <option value="">Todo</option><option value="bajo">Stock bajo</option>
                        </Form.Select>
                    </Form.Group></div>
                </div>
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <p className="small text-body-secondary mb-0" role="status">{listado.cargando ? 'Buscando productos…' : listado.error ? 'Inventario no disponible' : (meta?.total ?? 0)+' '+(meta?.total === 1 ? 'producto' : 'productos')+(hayFiltros ? ' con los filtros actuales' : ' con control de stock')}</p>
                    {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                </div>
            </div>} />
        <Outlet context={{ items, cargando: listado.cargando, error: listado.error, recargar: listado.recargar,
            onGuardado: (mensaje) => { notificar(mensaje); listado.recargar(); } } satisfies InventarioContexto} />
    </>;
}
