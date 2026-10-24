import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Badge, Button, Form, Spinner } from 'react-bootstrap';
import { Link, Outlet, useLocation, useSearchParams } from 'react-router-dom';
import { ConfirmModal, DataTable, Icon, PageHeader, SearchInput, TableActions } from '@/components/ui';
import type { Column } from '@/components/ui';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Paginated } from '@/types/api';
import type { Caja, CobroCaja, SucursalCaja } from '@/types/caja';
import AvisoCobro, { formatoUf } from './AvisoCobro';

export interface CajasContexto {
    sucursales?: SucursalCaja[];
    errorListado: boolean;
    recargar: () => void;
    onGuardado: (caja: Caja, operacion: 'creada' | 'actualizada') => void;
}

export default function CajasIndex() {
    const location = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const [reiniciosBusqueda, setReiniciosBusqueda] = useState(0);
    const consulta = parametros.toString();
    const listado = useConsultaApi<Paginated<Caja> & { sucursales: SucursalCaja[]; precio_caja_uf: string | null }>('/cajas'+(consulta ? '?'+consulta : ''));
    const [cajaPorCambiar, setCajaPorCambiar] = useState<Caja | null>(null);
    const cotizacion = useConsultaApi<{ data: CobroCaja }>(cajaPorCambiar && !cajaPorCambiar.activa
        ? '/cajas/cotizacion?sucursal='+cajaPorCambiar.sucursal.id+'&caja='+cajaPorCambiar.id : null);
    const [aceptado, setAceptado] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const [errorEstado, setErrorEstado] = useState('');
    const envioEnCurso = useRef(false);
    const buscar = parametros.get('buscar') ?? '';
    const sucursal = parametros.get('sucursal') ?? '';
    const estado = parametros.get('estado') ?? '';
    const hayFiltros = Boolean(buscar || sucursal || estado);
    const meta = listado.datos?.meta;
    const cobro = cotizacion.datos?.data;

    useEffect(() => setAceptado(false), [cajaPorCambiar?.id, cobro?.cotizacion]);
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
    const cambiarEstado = async () => {
        if (!cajaPorCambiar || envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrorEstado('');
        try {
            const respuesta = await api.patch<{ data: Caja }>('/cajas/'+cajaPorCambiar.id+'/estado', {
                activa: !cajaPorCambiar.activa,
                ...(!cajaPorCambiar.activa ? { acepto_cobro: aceptado, cotizacion: cobro?.cotizacion } : {}),
            });
            notificar('Caja '+(respuesta.data.data.activa ? 'activada' : 'desactivada')+' correctamente.');
            setCajaPorCambiar(null);
            listado.recargar();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrorEstado(Object.values(validacion).join(' ') || mensajeDeError(exception));
            if (validacion.cotizacion || validacion.tarifa) { setAceptado(false); cotizacion.recargar(); }
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    const columnas: Column<Caja>[] = [
        { header: 'Sucursal', cell: (caja) => <>{caja.sucursal.nombre}{!caja.sucursal.activa && <div className="small text-body-secondary">Sucursal inactiva</div>}</> },
        { header: 'Caja', mobile: 'title', cell: (caja) => caja.nombre },
        { header: 'Cobro', cell: (caja) => caja.es_incluida ? <Badge bg="light" text="dark" className="fw-normal">Incluida</Badge>
            : caja.precio_contratado_uf !== null ? <><span className="text-nowrap">{formatoUf(caja.precio_contratado_uf)} UF/mes</span><div className="small text-body-secondary">+ IVA</div></>
            : <span className="text-body-secondary">Sin cobro</span> },
        { header: 'Estado', cell: (caja) => <Badge pill bg={caja.activa ? 'success-subtle' : 'secondary-subtle'} text={caja.activa ? 'success' : 'secondary'}>{caja.activa ? 'Activa' : 'Inactiva'}</Badge> },
        { header: 'Acciones', mobile: 'actions', cell: (caja) => <TableActions recordLabel={caja.nombre+' de '+caja.sucursal.nombre}
            primaryAction={{ label: 'Editar', icon: 'pencil', to: '/cajas/'+caja.id+'/editar'+location.search }}
            actions={[{ label: caja.activa ? 'Desactivar' : 'Activar', icon: caja.activa ? 'toggle-off' : 'toggle-on',
                variant: caja.activa ? 'danger' : undefined, disabled: !caja.activa && !caja.sucursal.activa,
                disabledReason: 'Activa primero la sucursal.', onClick: () => { setErrorEstado(''); setCajaPorCambiar(caja); },
            }]} /> },
    ];
    let status: ReactNode;
    if (listado.error) {
        status = <div className="p-3" role="alert"><p>No se pudo cargar el listado de cajas. {mensajeDeError(listado.error)}</p><Button variant="outline-secondary" onClick={listado.recargar}>Reintentar</Button></div>;
    } else if (listado.cargando) {
        status = <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando cajas…</div>;
    }
    const bloqueaActivacion = cajaPorCambiar && !cajaPorCambiar.activa && (
        !cajaPorCambiar.sucursal.activa || cotizacion.cargando || Boolean(cotizacion.error) || !cobro || cobro.total_mensual_uf === null || (cobro.requiere_aceptacion && !aceptado)
    );

    return <>
        <PageHeader title="Cajas" subtitle="Administra las cajas de tu negocio, ordenadas por sucursal."
            actions={<Link to={'/cajas/nueva'+location.search} className="btn btn-primary"><Icon name="plus-lg" className="me-2" />Nueva caja</Link>} />
        <p className="small text-body-secondary">Cada sucursal incluye una caja activa.
            {listado.datos?.precio_caja_uf != null && <> Tarifa para nuevas cajas adicionales: <strong>{formatoUf(listado.datos.precio_caja_uf)} UF/mes + IVA</strong>.</>}
        </p>
        <DataTable columns={columnas} rows={listado.datos?.data ?? []} rowKey={(caja) => caja.id} caption="Cajas del negocio"
            status={status} busy={listado.cargando} meta={meta}
            onPageChange={(pagina) => setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(pagina)); return nuevos; })}
            emptyMessage={hayFiltros ? 'No encontramos cajas con estos filtros.' : 'Cada nueva sucursal se crea con su primera caja incluida.'}
            emptyAction={hayFiltros ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todas las cajas</Button> : undefined}
            toolbar={<div role="search" aria-label="Buscar y filtrar cajas">
                <div className="table-toolbar align-items-end">
                    <div className="table-toolbar__search"><Form.Label htmlFor="cajas-buscar">Buscar caja</Form.Label>
                        <SearchInput id="cajas-buscar" key={reiniciosBusqueda} value={buscar} label="Buscar caja por nombre" placeholder="Nombre de la caja" maxLength={100} onChange={(valor) => filtrar('buscar', valor)} />
                    </div>
                    <div className="table-toolbar__filters">
                        <Form.Group controlId="cajas-sucursal"><Form.Label>Sucursal</Form.Label><Form.Select value={sucursal} onChange={(event) => filtrar('sucursal', event.target.value)}>
                            <option value="">Todas las sucursales</option>{listado.datos?.sucursales.map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.nombre}{!opcion.activa ? ' (inactiva)' : ''}</option>)}
                        </Form.Select></Form.Group>
                        <Form.Group controlId="cajas-estado"><Form.Label>Estado</Form.Label><Form.Select value={estado} onChange={(event) => filtrar('estado', event.target.value)}>
                            <option value="">Todos los estados</option><option value="activa">Activas</option><option value="inactiva">Inactivas</option>
                        </Form.Select></Form.Group>
                    </div>
                </div>
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <p className="small text-body-secondary mb-0" role="status">{listado.cargando ? 'Buscando cajas…' : listado.error ? 'Listado no disponible' : (meta?.total ?? 0)+' '+(meta?.total === 1 ? 'caja' : 'cajas')+(hayFiltros ? ' con los filtros actuales' : ' en tu negocio')}</p>
                    {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                </div>
            </div>} />
        <Outlet context={{ sucursales: listado.datos?.sucursales, errorListado: Boolean(listado.error), recargar: listado.recargar,
            onGuardado: (caja, operacion) => { notificar('Caja '+caja.nombre+' '+operacion+' correctamente.'); listado.recargar(); },
        } satisfies CajasContexto} />
        {cajaPorCambiar && <ConfirmModal title={cajaPorCambiar.activa ? '¿Desactivar caja?' : '¿Activar caja?'}
            confirmLabel={cajaPorCambiar.activa ? 'Desactivar' : 'Activar'} variant={cajaPorCambiar.activa ? 'danger' : 'primary'}
            processing={procesando} confirmDisabled={Boolean(bloqueaActivacion)} error={errorEstado}
            onConfirm={() => void cambiarEstado()} onCancel={() => setCajaPorCambiar(null)}>
            <p><strong>{cajaPorCambiar.nombre}</strong> · {cajaPorCambiar.sucursal.nombre}</p>
            <p>{cajaPorCambiar.activa ? 'Dejará de estar disponible. Sus datos se conservarán y dejará de generar cobro desde hoy.' : 'Volverá a estar disponible en esta sucursal.'}</p>
            {cajaPorCambiar.activa && cajaPorCambiar.es_incluida && <p>Si queda otra caja activa en la sucursal, pasará a estar incluida y dejará de generar cobro adicional.</p>}
            {!cajaPorCambiar.activa && (cotizacion.cargando ? <p role="status">Consultando condiciones del cobro…</p>
                : cotizacion.error ? <div role="alert"><p>No se pudo consultar el cobro.</p><Button variant="outline-secondary" onClick={cotizacion.recargar}>Reintentar</Button></div>
                : cobro && <AvisoCobro cobro={cobro} aceptado={aceptado} onChange={setAceptado} disabled={procesando} />)}
        </ConfirmModal>}
    </>;
}
