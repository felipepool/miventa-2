import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Badge, Button, Form, Spinner } from 'react-bootstrap';
import { Link, Outlet, useLocation, useSearchParams } from 'react-router-dom';
import { ConfirmModal, DataTable, Icon, PageHeader, SearchInput, TableActions } from '@/components/ui';
import type { Column } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Paginated } from '@/types/api';
import type { CobroSucursal, Sucursal } from '@/types/sucursal';
import AvisoCobro, { formatoUf } from './AvisoCobro';

export interface SucursalesContexto {
    cobro?: CobroSucursal;
    recargar: () => void;
    onGuardado: (sucursal: Sucursal, operacion: 'creada' | 'actualizada') => void;
}

export default function SucursalesIndex() {
    const location = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const [reiniciosBusqueda, setReiniciosBusqueda] = useState(0);
    const consulta = parametros.toString();
    const listado = useConsultaApi<Paginated<Sucursal> & { cobro_sucursal: CobroSucursal }>('/sucursales'+(consulta ? '?'+consulta : ''));
    const { refresh } = useAuth();
    const [sucursalPorCambiar, setSucursalPorCambiar] = useState<Sucursal | null>(null);
    const cotizacion = useConsultaApi<{ data: CobroSucursal }>(sucursalPorCambiar && !sucursalPorCambiar.activa ? '/sucursales/'+sucursalPorCambiar.id+'/cotizacion' : null);
    const [aceptado, setAceptado] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const [errorEstado, setErrorEstado] = useState('');
    const envioEnCurso = useRef(false);
    const buscar = parametros.get('buscar') ?? '';
    const estado = parametros.get('estado') ?? '';
    const hayFiltros = Boolean(buscar || estado);
    const meta = listado.datos?.meta;
    const cobro = listado.datos?.cobro_sucursal;
    const cobroActivacion = cotizacion.datos?.data;

    useEffect(() => setAceptado(false), [sucursalPorCambiar?.id, cobroActivacion?.cotizacion]);
    useEffect(() => {
        if (meta && meta.current_page > meta.last_page) {
            setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(meta.last_page)); return nuevos; }, { replace: true });
        }
    }, [meta, setParametros]);

    const filtrar = (campo: string, valor: string) => {
        setParametros((actuales) => {
            const nuevos = new URLSearchParams(actuales);
            if (valor) { nuevos.set(campo, valor); } else { nuevos.delete(campo); }
            nuevos.delete('page');
            return nuevos;
        }, { replace: campo === 'buscar' });
    };
    const limpiarFiltros = () => { setReiniciosBusqueda((actual) => actual + 1); setParametros({}); };
    const actualizarContexto = () => { listado.recargar(); void refresh().catch(() => {}); };

    const cambiarEstado = async () => {
        if (!sucursalPorCambiar || envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrorEstado('');
        try {
            const respuesta = await api.patch<{ data: Sucursal }>('/sucursales/'+sucursalPorCambiar.id+'/estado', {
                activa: !sucursalPorCambiar.activa,
                ...(!sucursalPorCambiar.activa ? { acepto_cobro: aceptado, cotizacion: cobroActivacion?.cotizacion } : {}),
            });
            notificar('Sucursal '+(respuesta.data.data.activa ? 'activada' : 'desactivada')+' correctamente.');
            setSucursalPorCambiar(null);
            actualizarContexto();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrorEstado(Object.values(validacion).join(' ') || mensajeDeError(exception));
            if (validacion.cotizacion || validacion.tarifa) { setAceptado(false); cotizacion.recargar(); }
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    const columnas: Column<Sucursal>[] = [
        { header: 'Nombre', mobile: 'title', cell: (sucursal) => <>
            {sucursal.nombre}{sucursal.es_principal && <Badge bg="light" text="dark" className="ms-2 fw-normal">Principal</Badge>}
            {sucursal.direccion && <div className="small text-body-secondary fw-normal">{sucursal.direccion}</div>}
        </> },
        { header: 'Teléfono', cell: (sucursal) => sucursal.telefono || '—' },
        { header: 'Cajas', cell: (sucursal) => sucursal.cajas_count },
        { header: 'Usuarios asignados', cell: (sucursal) => sucursal.usuarios_count },
        { header: 'Estado', cell: (sucursal) => <Badge pill bg={sucursal.activa ? 'success-subtle' : 'secondary-subtle'} text={sucursal.activa ? 'success' : 'secondary'}>{sucursal.activa ? 'Activa' : 'Inactiva'}</Badge> },
        { header: 'Acciones', mobile: 'actions', cell: (sucursal) => <TableActions recordLabel={sucursal.nombre}
            primaryAction={{ label: 'Editar', icon: 'pencil', to: '/sucursales/'+sucursal.id+'/editar'+location.search }}
            actions={[{ label: sucursal.activa ? 'Desactivar' : 'Activar', icon: sucursal.activa ? 'shop-window' : 'shop',
                variant: sucursal.activa ? 'danger' : undefined, disabled: sucursal.es_principal,
                disabledReason: 'La sucursal principal no se puede desactivar.',
                onClick: () => { setErrorEstado(''); setSucursalPorCambiar(sucursal); },
            }]} /> },
    ];
    let status: ReactNode;
    if (listado.error) {
        status = <div className="p-3" role="alert"><p>No se pudo cargar el listado de sucursales. {mensajeDeError(listado.error)}</p><Button variant="outline-secondary" onClick={listado.recargar}>Reintentar</Button></div>;
    } else if (listado.cargando) {
        status = <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando sucursales…</div>;
    }
    const bloqueaActivacion = sucursalPorCambiar && !sucursalPorCambiar.activa && (
        cotizacion.cargando || Boolean(cotizacion.error) || !cobroActivacion || cobroActivacion.total_mensual_uf === null || (cobroActivacion.requiere_aceptacion && !aceptado)
    );

    return <>
        <PageHeader title="Sucursales" subtitle="Administra los locales de tu negocio y sus cajas incluidas."
            actions={<Link to={'/sucursales/nueva'+location.search} className="btn btn-primary"><Icon name="plus-lg" className="me-2" />Nueva sucursal</Link>} />
        <p className="small text-body-secondary">Tu plan incluye una sucursal y cada sucursal incluye una caja.
            {cobro?.precio_sucursal_uf !== null && cobro?.precio_sucursal_uf !== undefined && <> Tarifa para nuevas sucursales adicionales: <strong>{formatoUf(cobro.precio_sucursal_uf)} UF/mes + IVA</strong>.</>}
        </p>
        <DataTable columns={columnas} rows={listado.datos?.data ?? []} rowKey={(sucursal) => sucursal.id} caption="Sucursales del negocio"
            status={status} busy={listado.cargando} meta={meta}
            onPageChange={(pagina) => setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(pagina)); return nuevos; })}
            emptyMessage={hayFiltros ? 'No encontramos sucursales con estos filtros.' : 'Agrega la primera sucursal de tu negocio. Incluye una caja sin costo adicional.'}
            emptyAction={hayFiltros ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todas las sucursales</Button> : <Link to="/sucursales/nueva" className="btn btn-primary">Nueva sucursal</Link>}
            toolbar={<div role="search" aria-label="Buscar y filtrar sucursales">
                <div className="table-toolbar align-items-end">
                    <div className="table-toolbar__search"><Form.Label htmlFor="sucursales-buscar">Buscar sucursal</Form.Label>
                        <SearchInput id="sucursales-buscar" key={reiniciosBusqueda} value={buscar} label="Buscar sucursal por nombre o dirección" placeholder="Nombre o dirección" maxLength={100} onChange={(valor) => filtrar('buscar', valor)} />
                    </div>
                    <div className="table-toolbar__filters"><Form.Group controlId="sucursales-estado"><Form.Label>Estado</Form.Label>
                        <Form.Select className="w-100" value={estado} onChange={(event) => filtrar('estado', event.target.value)}>
                            <option value="">Todos los estados</option><option value="activa">Activas</option><option value="inactiva">Inactivas</option>
                        </Form.Select>
                    </Form.Group></div>
                </div>
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <p className="small text-body-secondary mb-0" role="status">{listado.cargando ? 'Buscando sucursales…' : listado.error ? 'Listado no disponible' : (meta?.total ?? 0)+' '+(meta?.total === 1 ? 'sucursal' : 'sucursales')+(hayFiltros ? ' con los filtros actuales' : ' en tu negocio')}</p>
                    {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                </div>
            </div>} />
        <Outlet context={{ cobro, recargar: listado.recargar, onGuardado: (sucursal, operacion) => { notificar('Sucursal '+sucursal.nombre+' '+operacion+' correctamente.'); actualizarContexto(); } } satisfies SucursalesContexto} />
        {sucursalPorCambiar && <ConfirmModal title={sucursalPorCambiar.activa ? '¿Desactivar sucursal?' : '¿Activar sucursal?'}
            confirmLabel={sucursalPorCambiar.activa ? 'Desactivar' : 'Activar'} variant={sucursalPorCambiar.activa ? 'danger' : 'primary'}
            processing={procesando} confirmDisabled={Boolean(bloqueaActivacion)} error={errorEstado}
            onConfirm={() => void cambiarEstado()} onCancel={() => setSucursalPorCambiar(null)}>
            <p><strong>{sucursalPorCambiar.nombre}</strong> {sucursalPorCambiar.activa ? 'dejará de estar disponible para trabajar. Sus datos, cajas y asignaciones se conservarán y los adicionales dejarán de cobrarse desde hoy.' : 'volverá a estar disponible para sus usuarios asignados.'}</p>
            {!sucursalPorCambiar.activa && (cotizacion.cargando ? <p role="status">Consultando condiciones del cobro…</p>
                : cotizacion.error ? <div role="alert"><p>No se pudo consultar el cobro.</p><Button variant="outline-secondary" onClick={cotizacion.recargar}>Reintentar</Button></div>
                : cobroActivacion && <AvisoCobro cobro={cobroActivacion} aceptado={aceptado} onChange={setAceptado} disabled={procesando} />)}
        </ConfirmModal>}
    </>;
}
