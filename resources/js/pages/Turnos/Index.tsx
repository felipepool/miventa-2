import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { Badge, Button, Form, Spinner } from 'react-bootstrap';
import { Link, useSearchParams } from 'react-router-dom';
import { DataTable, PageHeader, TableActions } from '@/components/ui';
import type { Column } from '@/components/ui';
import { describirDiferencia, formatearFechaHora, formatearMonto } from '@/lib/caja';
import { mensajeDeError } from '@/lib/http';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { useTitulo } from '@/lib/useTitulo';
import type { Paginated } from '@/types/api';
import type { TurnoCaja } from '@/types/turno';

interface ListadoTurnos extends Paginated<TurnoCaja> {
    cajas: { id: string; nombre: string }[];
    usuarios: { id: string; name: string }[];
    puede_ver_esperado: boolean;
}

export default function TurnosIndex() {
    useTitulo('Turnos');
    const [parametros, setParametros] = useSearchParams();
    const consulta = parametros.toString();
    const listado = useConsultaApi<ListadoTurnos>('/turnos'+(consulta ? '?'+consulta : ''));
    const caja = parametros.get('caja') ?? '';
    const usuario = parametros.get('usuario') ?? '';
    const estado = parametros.get('estado') ?? '';
    const desde = parametros.get('desde') ?? '';
    const hasta = parametros.get('hasta') ?? '';
    const hayFiltros = Boolean(caja || usuario || estado || desde || hasta);
    const meta = listado.datos?.meta;
    const puedeVerEsperado = listado.datos?.puede_ver_esperado ?? false;

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
    });
    const limpiarFiltros = () => setParametros({});

    const columnas: Column<TurnoCaja>[] = [
        { header: 'Turno', mobile: 'title', cell: (turno) => <><strong>{turno.caja?.nombre} · Turno {turno.numero}</strong><small className="d-block text-body-secondary">{turno.usuario}</small></> },
        { header: 'Apertura', cell: (turno) => formatearFechaHora(turno.abierta_at) },
        { header: 'Cierre', cell: (turno) => turno.cerrada_at ? formatearFechaHora(turno.cerrada_at) : <Badge pill bg="success-subtle" text="success">Abierto</Badge> },
        { header: 'Monto inicial', cell: (turno) => formatearMonto(turno.monto_inicial) },
        ...(puedeVerEsperado ? [{ header: 'Esperado', cell: (turno: TurnoCaja) => turno.monto_esperado === undefined || turno.monto_esperado === null ? '—' : formatearMonto(turno.monto_esperado) }] : []),
        { header: 'Contado', cell: (turno) => turno.monto_contado === null ? '—' : formatearMonto(turno.monto_contado) },
        ...(puedeVerEsperado ? [{ header: 'Diferencia', cell: (turno: TurnoCaja) => turno.diferencia === undefined || turno.diferencia === null ? '—'
            : <Badge pill bg={turno.diferencia === 0 ? 'success-subtle' : 'warning-subtle'} text={turno.diferencia === 0 ? 'success' : 'warning-emphasis'}>{describirDiferencia(turno.diferencia)}</Badge> }] : []),
        { header: 'Acciones', mobile: 'actions', cell: (turno) => <TableActions recordLabel={'turno '+turno.numero+' de '+(turno.caja?.nombre ?? 'caja')}
            primaryAction={{ label: 'Ver', icon: 'eye', to: '/turnos/'+turno.id }} actions={[]} /> },
    ];
    let status: ReactNode;
    if (listado.error) {
        status = <div className="p-3" role="alert"><p>No se pudo cargar el historial de turnos. {mensajeDeError(listado.error)}</p><Button variant="outline-secondary" onClick={listado.recargar}>Reintentar</Button></div>;
    } else if (listado.cargando) {
        status = <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando turnos…</div>;
    }

    return <>
        <PageHeader title="Turnos" subtitle="Historial de turnos de caja de esta sucursal." actions={<Link to="/caja" className="btn btn-outline-secondary">Ir a Mi caja</Link>} />
        <DataTable columns={columnas} rows={listado.datos?.data ?? []} rowKey={(turno) => turno.id} caption="Turnos de caja de la sucursal"
            status={status} busy={listado.cargando} meta={meta} emptyIcon="clock-history"
            onPageChange={(pagina) => setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(pagina)); return nuevos; })}
            emptyMessage={hayFiltros ? 'No encontramos turnos con estos filtros.' : 'Todavía no hay turnos. Abre tu caja para empezar.'}
            emptyAction={hayFiltros ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todos los turnos</Button> : <Link to="/caja" className="btn btn-primary">Abrir caja</Link>}
            toolbar={<div role="search" aria-label="Filtrar turnos">
                <div className="table-toolbar align-items-end">
                    <div className="table-toolbar__filters"><Form.Group controlId="turnos-caja"><Form.Label>Caja</Form.Label>
                        <Form.Select className="w-100" value={caja} disabled={!listado.datos} onChange={(event) => filtrar('caja', event.target.value)}>
                            <option value="">Todas</option>{(listado.datos?.cajas ?? []).map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.nombre}</option>)}
                        </Form.Select></Form.Group></div>
                    {(listado.datos?.usuarios.length ?? 0) > 0 && <div className="table-toolbar__filters"><Form.Group controlId="turnos-usuario"><Form.Label>Usuario</Form.Label>
                        <Form.Select className="w-100" value={usuario} onChange={(event) => filtrar('usuario', event.target.value)}>
                            <option value="">Todos</option>{listado.datos?.usuarios.map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.name}</option>)}
                        </Form.Select></Form.Group></div>}
                    <div className="table-toolbar__filters"><Form.Group controlId="turnos-estado"><Form.Label>Estado</Form.Label>
                        <Form.Select className="w-100" value={estado} onChange={(event) => filtrar('estado', event.target.value)}>
                            <option value="">Todos</option><option value="abierto">Abiertos</option><option value="cerrado">Cerrados</option>
                        </Form.Select></Form.Group></div>
                    <div className="table-toolbar__filters"><Form.Group controlId="turnos-desde"><Form.Label>Desde</Form.Label>
                        <Form.Control type="date" value={desde} max={hasta || undefined} onChange={(event) => filtrar('desde', event.target.value)} /></Form.Group></div>
                    <div className="table-toolbar__filters"><Form.Group controlId="turnos-hasta"><Form.Label>Hasta</Form.Label>
                        <Form.Control type="date" value={hasta} min={desde || undefined} onChange={(event) => filtrar('hasta', event.target.value)} /></Form.Group></div>
                </div>
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <p className="small text-body-secondary mb-0" role="status">{listado.cargando ? 'Buscando turnos…' : listado.error ? 'Historial no disponible' : (meta?.total ?? 0)+' '+(meta?.total === 1 ? 'turno' : 'turnos')+(hayFiltros ? ' con los filtros actuales' : ' en esta sucursal')}</p>
                    {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                </div>
            </div>} />
    </>;
}

