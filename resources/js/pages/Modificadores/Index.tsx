import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Badge, Button, Form, Spinner } from 'react-bootstrap';
import { Link, Outlet, useLocation, useSearchParams } from 'react-router-dom';
import { ConfirmModal, DataTable, Icon, PageHeader, SearchInput, TableActions } from '@/components/ui';
import type { Column } from '@/components/ui';
import { ETIQUETAS_SELECCION, resumenDeReglas } from '@/lib/modificadores';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Paginated } from '@/types/api';
import type { GrupoModificador } from '@/types/catalogo';

export interface ModificadoresContexto {
    onGuardado: (grupo: GrupoModificador, operacion: 'creado' | 'actualizado') => void;
}

export default function ModificadoresIndex() {
    const location = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const [reiniciosBusqueda, setReiniciosBusqueda] = useState(0);
    const consulta = parametros.toString();
    const listado = useConsultaApi<Paginated<GrupoModificador>>('/grupos-modificadores'+(consulta ? '?'+consulta : ''));
    const [grupoPorCambiar, setGrupoPorCambiar] = useState<GrupoModificador | null>(null);
    const [procesando, setProcesando] = useState(false);
    const [errorEstado, setErrorEstado] = useState('');
    const envioEnCurso = useRef(false);
    const buscar = parametros.get('buscar') ?? '';
    const estado = parametros.get('estado') ?? '';
    const hayFiltros = Boolean(buscar || estado);
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
    }, { replace: campo === 'buscar' });
    const limpiarFiltros = () => { setReiniciosBusqueda((actual) => actual + 1); setParametros({}); };

    const cambiarEstado = async () => {
        if (!grupoPorCambiar || envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrorEstado('');
        try {
            const respuesta = await api.patch<{ data: GrupoModificador }>('/grupos-modificadores/'+grupoPorCambiar.id+'/estado', { activo: !grupoPorCambiar.activo });
            notificar('Grupo '+(respuesta.data.data.activo ? 'activado' : 'desactivado')+' correctamente.');
            setGrupoPorCambiar(null);
            listado.recargar();
        } catch (exception) {
            setErrorEstado(Object.values(erroresDeValidacion(exception)).join(' ') || mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    const columnas: Column<GrupoModificador>[] = [
        { header: 'Grupo', mobile: 'title', cell: (grupo) => <><strong>{grupo.nombre}</strong>{grupo.referencia && <small className="d-block text-body-secondary">{grupo.referencia}</small>}</> },
        { header: 'Selección', cell: (grupo) => <>{ETIQUETAS_SELECCION[grupo.tipo_seleccion]}<small className="d-block text-body-secondary">{resumenDeReglas(grupo)}</small></> },
        { header: 'Modificadores', cell: (grupo) => grupo.modificadores.filter((modificador) => modificador.activo).map((modificador) => modificador.nombre).join(', ') },
        { header: 'Productos', cell: (grupo) => grupo.productos_count ?? 0 },
        { header: 'Estado', cell: (grupo) => <Badge pill bg={grupo.activo ? 'success-subtle' : 'secondary-subtle'} text={grupo.activo ? 'success' : 'secondary'}>{grupo.activo ? 'Activo' : 'Inactivo'}</Badge> },
        { header: 'Acciones', mobile: 'actions', cell: (grupo) => <TableActions recordLabel={grupo.nombre}
            primaryAction={{ label: 'Editar', icon: 'pencil', to: '/modificadores/'+grupo.id+'/editar'+location.search }}
            actions={[{ label: grupo.activo ? 'Desactivar' : 'Activar', icon: grupo.activo ? 'toggle-off' : 'toggle-on', variant: grupo.activo ? 'danger' : undefined,
                onClick: () => { setErrorEstado(''); setGrupoPorCambiar(grupo); } }]} /> },
    ];
    let status: ReactNode;
    if (listado.error) {
        status = <div className="p-3" role="alert"><p>No se pudo cargar el listado de modificadores. {mensajeDeError(listado.error)}</p><Button variant="outline-secondary" onClick={listado.recargar}>Reintentar</Button></div>;
    } else if (listado.cargando) {
        status = <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando modificadores…</div>;
    }

    return <>
        <PageHeader title="Modificadores" subtitle="Crea grupos de opciones, como tipo de pan, agregados o salsas, y asígnalos a tus productos."
            actions={<Link to={'/modificadores/nuevo'+location.search} className="btn btn-primary"><Icon name="plus-lg" className="me-2" />Nuevo grupo</Link>} />
        <DataTable columns={columnas} rows={listado.datos?.data ?? []} rowKey={(grupo) => grupo.id} caption="Grupos de modificadores del negocio"
            status={status} busy={listado.cargando} meta={meta} emptyIcon="sliders"
            onPageChange={(pagina) => setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(pagina)); return nuevos; })}
            emptyMessage={hayFiltros ? 'No encontramos grupos con estos filtros.' : 'Todavía no tienes grupos de modificadores. Crea el primero.'}
            emptyAction={hayFiltros ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todos los grupos</Button> : <Link to="/modificadores/nuevo" className="btn btn-primary">Nuevo grupo</Link>}
            toolbar={<div role="search" aria-label="Buscar y filtrar grupos de modificadores">
                <div className="table-toolbar align-items-end">
                    <div className="table-toolbar__search"><Form.Label htmlFor="modificadores-buscar">Buscar grupo</Form.Label>
                        <SearchInput id="modificadores-buscar" key={reiniciosBusqueda} value={buscar} label="Buscar grupo por nombre o referencia" placeholder="Nombre o referencia" maxLength={100} onChange={(valor) => filtrar('buscar', valor)} />
                    </div>
                    <div className="table-toolbar__filters"><Form.Group controlId="modificadores-estado"><Form.Label>Estado</Form.Label>
                        <Form.Select className="w-100" value={estado} onChange={(event) => filtrar('estado', event.target.value)}>
                            <option value="">Todos los estados</option><option value="activo">Activos</option><option value="inactivo">Inactivos</option>
                        </Form.Select>
                    </Form.Group></div>
                </div>
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <p className="small text-body-secondary mb-0" role="status">{listado.cargando ? 'Buscando grupos…' : listado.error ? 'Listado no disponible' : (meta?.total ?? 0)+' '+(meta?.total === 1 ? 'grupo' : 'grupos')+(hayFiltros ? ' con los filtros actuales' : ' en tu negocio')}</p>
                    {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                </div>
            </div>} />
        <Outlet context={{ onGuardado: (grupo, operacion) => { notificar('Grupo '+grupo.nombre+' '+operacion+' correctamente.'); listado.recargar(); } } satisfies ModificadoresContexto} />
        {grupoPorCambiar && <ConfirmModal title={grupoPorCambiar.activo ? '¿Desactivar grupo?' : '¿Activar grupo?'}
            confirmLabel={grupoPorCambiar.activo ? 'Desactivar' : 'Activar'} variant={grupoPorCambiar.activo ? 'danger' : 'primary'}
            processing={procesando} error={errorEstado} onConfirm={() => void cambiarEstado()} onCancel={() => setGrupoPorCambiar(null)}>
            <p><strong>{grupoPorCambiar.nombre}</strong></p>
            <p>{grupoPorCambiar.activo ? 'No podrá asignarse a productos nuevos. Los productos que ya lo usan lo conservan.' : 'Volverá a estar disponible para tus productos.'}</p>
        </ConfirmModal>}
    </>;
}

