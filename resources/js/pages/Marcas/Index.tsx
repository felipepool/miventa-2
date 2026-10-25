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
import type { ElementoCatalogo } from '@/types/catalogo';

export interface MarcasContexto {
    onGuardado: (marca: ElementoCatalogo, operacion: 'creada' | 'actualizada') => void;
}

export default function MarcasIndex() {
    const location = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const [reiniciosBusqueda, setReiniciosBusqueda] = useState(0);
    const consulta = parametros.toString();
    const listado = useConsultaApi<Paginated<ElementoCatalogo>>('/marcas'+(consulta ? '?'+consulta : ''));
    const [marcaPorCambiar, setMarcaPorCambiar] = useState<ElementoCatalogo | null>(null);
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
        if (!marcaPorCambiar || envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrorEstado('');
        try {
            const respuesta = await api.patch<{ data: ElementoCatalogo }>('/marcas/'+marcaPorCambiar.id+'/estado', { activa: !marcaPorCambiar.activa });
            notificar('Marca '+(respuesta.data.data.activa ? 'activada' : 'desactivada')+' correctamente.');
            setMarcaPorCambiar(null);
            listado.recargar();
        } catch (exception) {
            setErrorEstado(Object.values(erroresDeValidacion(exception)).join(' ') || mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    const columnas: Column<ElementoCatalogo>[] = [
        { header: 'Nombre', mobile: 'title', cell: (marca) => marca.nombre },
        { header: 'Productos', cell: (marca) => marca.productos_count },
        { header: 'Estado', cell: (marca) => <Badge pill bg={marca.activa ? 'success-subtle' : 'secondary-subtle'} text={marca.activa ? 'success' : 'secondary'}>{marca.activa ? 'Activa' : 'Inactiva'}</Badge> },
        { header: 'Acciones', mobile: 'actions', cell: (marca) => <TableActions recordLabel={marca.nombre}
            primaryAction={{ label: 'Editar', icon: 'pencil', to: '/marcas/'+marca.id+'/editar'+location.search }}
            actions={[{ label: marca.activa ? 'Desactivar' : 'Activar', icon: marca.activa ? 'toggle-off' : 'toggle-on',
                variant: marca.activa ? 'danger' : undefined, onClick: () => { setErrorEstado(''); setMarcaPorCambiar(marca); },
            }]} /> },
    ];
    let status: ReactNode;
    if (listado.error) {
        status = <div className="p-3" role="alert"><p>No se pudo cargar el listado de marcas. {mensajeDeError(listado.error)}</p><Button variant="outline-secondary" onClick={listado.recargar}>Reintentar</Button></div>;
    } else if (listado.cargando) {
        status = <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando marcas…</div>;
    }

    return <>
        <PageHeader title="Marcas" subtitle="Organiza tus productos por marca."
            actions={<Link to={'/marcas/nueva'+location.search} className="btn btn-primary"><Icon name="plus-lg" className="me-2" />Nueva marca</Link>} />
        <DataTable columns={columnas} rows={listado.datos?.data ?? []} rowKey={(marca) => marca.id} caption="Marcas del negocio"
            status={status} busy={listado.cargando} meta={meta}
            onPageChange={(pagina) => setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(pagina)); return nuevos; })}
            emptyMessage={hayFiltros ? 'No encontramos marcas con estos filtros.' : 'Todavía no tienes marcas. Agrega la primera.'}
            emptyAction={hayFiltros ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todas las marcas</Button> : <Link to="/marcas/nueva" className="btn btn-primary">Nueva marca</Link>}
            toolbar={<div role="search" aria-label="Buscar y filtrar marcas">
                <div className="table-toolbar align-items-end">
                    <div className="table-toolbar__search"><Form.Label htmlFor="marcas-buscar">Buscar marca</Form.Label>
                        <SearchInput id="marcas-buscar" key={reiniciosBusqueda} value={buscar} label="Buscar marca por nombre" placeholder="Nombre de la marca" maxLength={100} onChange={(valor) => filtrar('buscar', valor)} />
                    </div>
                    <div className="table-toolbar__filters"><Form.Group controlId="marcas-estado"><Form.Label>Estado</Form.Label>
                        <Form.Select className="w-100" value={estado} onChange={(event) => filtrar('estado', event.target.value)}>
                            <option value="">Todos los estados</option><option value="activa">Activas</option><option value="inactiva">Inactivas</option>
                        </Form.Select>
                    </Form.Group></div>
                </div>
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <p className="small text-body-secondary mb-0" role="status">{listado.cargando ? 'Buscando marcas…' : listado.error ? 'Listado no disponible' : (meta?.total ?? 0)+' '+(meta?.total === 1 ? 'marca' : 'marcas')+(hayFiltros ? ' con los filtros actuales' : ' en tu negocio')}</p>
                    {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                </div>
            </div>} />
        <Outlet context={{ onGuardado: (marca, operacion) => { notificar('Marca '+marca.nombre+' '+operacion+' correctamente.'); listado.recargar(); } } satisfies MarcasContexto} />
        {marcaPorCambiar && <ConfirmModal title={marcaPorCambiar.activa ? '¿Desactivar marca?' : '¿Activar marca?'}
            confirmLabel={marcaPorCambiar.activa ? 'Desactivar' : 'Activar'} variant={marcaPorCambiar.activa ? 'danger' : 'primary'}
            processing={procesando} error={errorEstado} onConfirm={() => void cambiarEstado()} onCancel={() => setMarcaPorCambiar(null)}>
            <p><strong>{marcaPorCambiar.nombre}</strong></p>
            <p>{marcaPorCambiar.activa ? 'No podrá asignarse a productos nuevos. Los productos que ya la usan la conservan.' : 'Volverá a estar disponible para tus productos.'}</p>
        </ConfirmModal>}
    </>;
}
