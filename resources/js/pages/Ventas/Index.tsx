import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { Badge, Button, Form, Spinner } from 'react-bootstrap';
import { Link, useSearchParams } from 'react-router-dom';
import { DataTable, PageHeader, TableActions } from '@/components/ui';
import type { Column } from '@/components/ui';
import { formatearFechaHora, formatearMonto } from '@/lib/caja';
import { mensajeDeError } from '@/lib/http';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { useTitulo } from '@/lib/useTitulo';
import type { Paginated } from '@/types/api';
import type { MedioPago, Venta } from '@/types/venta';

interface ListadoVentas extends Paginated<Venta> {
    usuarios: { id: string; name: string }[];
    medios_pago: { value: MedioPago; label: string }[];
}

export default function VentasIndex() {
    useTitulo('Historial de ventas');
    const [parametros, setParametros] = useSearchParams();
    const consulta = parametros.toString();
    const listado = useConsultaApi<ListadoVentas>('/ventas'+(consulta ? '?'+consulta : ''));
    const folio = parametros.get('folio') ?? '';
    const usuario = parametros.get('usuario') ?? '';
    const medio = parametros.get('medio') ?? '';
    const desde = parametros.get('desde') ?? '';
    const hasta = parametros.get('hasta') ?? '';
    const hayFiltros = Boolean(folio || usuario || medio || desde || hasta);
    const meta = listado.datos?.meta;

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
    }, { replace: campo === 'folio' });
    const limpiarFiltros = () => setParametros({});

    const columnas: Column<Venta>[] = [
        { header: 'Venta', mobile: 'title', cell: (venta) => <><strong>N° {venta.folio}</strong><small className="d-block text-body-secondary">{venta.vendedor}</small></> },
        { header: 'Fecha', cell: (venta) => formatearFechaHora(venta.pagada_at) },
        { header: 'Productos', cell: (venta) => venta.items_count ?? 0 },
        { header: 'Pago', cell: (venta) => (venta.pagos ?? []).map((pago) => pago.etiqueta).join(' + ') },
        { header: 'Total', cell: (venta) => <strong>{formatearMonto(venta.total)}</strong> },
        { header: 'Estado', cell: (venta) => <Badge pill bg={venta.estado === 'pagada' ? 'success-subtle' : 'secondary-subtle'} text={venta.estado === 'pagada' ? 'success' : 'secondary'}>{venta.estado === 'pagada' ? 'Pagada' : 'Anulada'}</Badge> },
        { header: 'Acciones', mobile: 'actions', cell: (venta) => <TableActions recordLabel={'venta '+venta.folio} primaryAction={{ label: 'Ver', icon: 'eye', to: '/ventas/'+venta.id }} actions={[]} /> },
    ];
    let status: ReactNode;
    if (listado.error) {
        status = <div className="p-3" role="alert"><p>No se pudo cargar el historial de ventas. {mensajeDeError(listado.error)}</p><Button variant="outline-secondary" onClick={listado.recargar}>Reintentar</Button></div>;
    } else if (listado.cargando) {
        status = <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando ventas…</div>;
    }

    return <>
        <PageHeader title="Historial de ventas" subtitle="Ventas registradas en esta sucursal." actions={<Link to="/pos" className="btn btn-primary">Ir a vender</Link>} />
        <DataTable columns={columnas} rows={listado.datos?.data ?? []} rowKey={(venta) => venta.id} caption="Ventas de la sucursal"
            status={status} busy={listado.cargando} meta={meta} emptyIcon="receipt"
            onPageChange={(pagina) => setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(pagina)); return nuevos; })}
            emptyMessage={hayFiltros ? 'No encontramos ventas con estos filtros.' : 'Todavía no hay ventas registradas.'}
            emptyAction={hayFiltros ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todas las ventas</Button> : <Link to="/pos" className="btn btn-primary">Ir a vender</Link>}
            toolbar={<div role="search" aria-label="Filtrar ventas">
                <div className="table-toolbar align-items-end">
                    <div className="table-toolbar__filters"><Form.Group controlId="ventas-folio"><Form.Label>N° de venta</Form.Label>
                        <Form.Control type="number" min="1" inputMode="numeric" value={folio} onChange={(event) => filtrar('folio', event.target.value)} /></Form.Group></div>
                    {(listado.datos?.usuarios.length ?? 0) > 0 && <div className="table-toolbar__filters"><Form.Group controlId="ventas-usuario"><Form.Label>Vendedor</Form.Label>
                        <Form.Select className="w-100" value={usuario} onChange={(event) => filtrar('usuario', event.target.value)}>
                            <option value="">Todos</option>{listado.datos?.usuarios.map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.name}</option>)}
                        </Form.Select></Form.Group></div>}
                    <div className="table-toolbar__filters"><Form.Group controlId="ventas-medio"><Form.Label>Medio de pago</Form.Label>
                        <Form.Select className="w-100" value={medio} disabled={!listado.datos} onChange={(event) => filtrar('medio', event.target.value)}>
                            <option value="">Todos</option>{(listado.datos?.medios_pago ?? []).map((opcion) => <option key={opcion.value} value={opcion.value}>{opcion.label}</option>)}
                        </Form.Select></Form.Group></div>
                    <div className="table-toolbar__filters"><Form.Group controlId="ventas-desde"><Form.Label>Desde</Form.Label>
                        <Form.Control type="date" value={desde} max={hasta || undefined} onChange={(event) => filtrar('desde', event.target.value)} /></Form.Group></div>
                    <div className="table-toolbar__filters"><Form.Group controlId="ventas-hasta"><Form.Label>Hasta</Form.Label>
                        <Form.Control type="date" value={hasta} min={desde || undefined} onChange={(event) => filtrar('hasta', event.target.value)} /></Form.Group></div>
                </div>
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <p className="small text-body-secondary mb-0" role="status">{listado.cargando ? 'Buscando ventas…' : listado.error ? 'Historial no disponible' : (meta?.total ?? 0)+' '+(meta?.total === 1 ? 'venta' : 'ventas')+(hayFiltros ? ' con los filtros actuales' : ' en esta sucursal')}</p>
                    {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                </div>
            </div>} />
    </>;
}
