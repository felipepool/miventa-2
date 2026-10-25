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

export interface CategoriasContexto {
    onGuardado: (categoria: ElementoCatalogo, operacion: 'creada' | 'actualizada') => void;
}

export default function CategoriasIndex() {
    const location = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const [reiniciosBusqueda, setReiniciosBusqueda] = useState(0);
    const consulta = parametros.toString();
    const listado = useConsultaApi<Paginated<ElementoCatalogo>>('/categorias'+(consulta ? '?'+consulta : ''));
    const [categoriaPorCambiar, setCategoriaPorCambiar] = useState<ElementoCatalogo | null>(null);
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
        if (!categoriaPorCambiar || envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrorEstado('');
        try {
            const respuesta = await api.patch<{ data: ElementoCatalogo }>('/categorias/'+categoriaPorCambiar.id+'/estado', { activa: !categoriaPorCambiar.activa });
            notificar('Categoría '+(respuesta.data.data.activa ? 'activada' : 'desactivada')+' correctamente.');
            setCategoriaPorCambiar(null);
            listado.recargar();
        } catch (exception) {
            setErrorEstado(Object.values(erroresDeValidacion(exception)).join(' ') || mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    const columnas: Column<ElementoCatalogo>[] = [
        { header: 'Nombre', mobile: 'title', cell: (categoria) => categoria.nombre },
        { header: 'Productos', cell: (categoria) => categoria.productos_count },
        { header: 'Estado', cell: (categoria) => <Badge pill bg={categoria.activa ? 'success-subtle' : 'secondary-subtle'} text={categoria.activa ? 'success' : 'secondary'}>{categoria.activa ? 'Activa' : 'Inactiva'}</Badge> },
        { header: 'Acciones', mobile: 'actions', cell: (categoria) => <TableActions recordLabel={categoria.nombre}
            primaryAction={{ label: 'Editar', icon: 'pencil', to: '/categorias/'+categoria.id+'/editar'+location.search }}
            actions={[{ label: categoria.activa ? 'Desactivar' : 'Activar', icon: categoria.activa ? 'toggle-off' : 'toggle-on',
                variant: categoria.activa ? 'danger' : undefined, onClick: () => { setErrorEstado(''); setCategoriaPorCambiar(categoria); },
            }]} /> },
    ];
    let status: ReactNode;
    if (listado.error) {
        status = <div className="p-3" role="alert"><p>No se pudo cargar el listado de categorías. {mensajeDeError(listado.error)}</p><Button variant="outline-secondary" onClick={listado.recargar}>Reintentar</Button></div>;
    } else if (listado.cargando) {
        status = <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando categorías…</div>;
    }

    return <>
        <PageHeader title="Categorías" subtitle="Organiza tus productos por categoría."
            actions={<Link to={'/categorias/nueva'+location.search} className="btn btn-primary"><Icon name="plus-lg" className="me-2" />Nueva categoría</Link>} />
        <DataTable columns={columnas} rows={listado.datos?.data ?? []} rowKey={(categoria) => categoria.id} caption="Categorías del negocio"
            status={status} busy={listado.cargando} meta={meta}
            onPageChange={(pagina) => setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(pagina)); return nuevos; })}
            emptyMessage={hayFiltros ? 'No encontramos categorías con estos filtros.' : 'Todavía no tienes categorías. Agrega la primera.'}
            emptyAction={hayFiltros ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todas las categorías</Button> : <Link to="/categorias/nueva" className="btn btn-primary">Nueva categoría</Link>}
            toolbar={<div role="search" aria-label="Buscar y filtrar categorías">
                <div className="table-toolbar align-items-end">
                    <div className="table-toolbar__search"><Form.Label htmlFor="categorias-buscar">Buscar categoría</Form.Label>
                        <SearchInput id="categorias-buscar" key={reiniciosBusqueda} value={buscar} label="Buscar categoría por nombre" placeholder="Nombre de la categoría" maxLength={100} onChange={(valor) => filtrar('buscar', valor)} />
                    </div>
                    <div className="table-toolbar__filters"><Form.Group controlId="categorias-estado"><Form.Label>Estado</Form.Label>
                        <Form.Select className="w-100" value={estado} onChange={(event) => filtrar('estado', event.target.value)}>
                            <option value="">Todos los estados</option><option value="activa">Activas</option><option value="inactiva">Inactivas</option>
                        </Form.Select>
                    </Form.Group></div>
                </div>
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <p className="small text-body-secondary mb-0" role="status">{listado.cargando ? 'Buscando categorías…' : listado.error ? 'Listado no disponible' : (meta?.total ?? 0)+' '+(meta?.total === 1 ? 'categoría' : 'categorías')+(hayFiltros ? ' con los filtros actuales' : ' en tu negocio')}</p>
                    {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                </div>
            </div>} />
        <Outlet context={{ onGuardado: (categoria, operacion) => { notificar('Categoría '+categoria.nombre+' '+operacion+' correctamente.'); listado.recargar(); } } satisfies CategoriasContexto} />
        {categoriaPorCambiar && <ConfirmModal title={categoriaPorCambiar.activa ? '¿Desactivar categoría?' : '¿Activar categoría?'}
            confirmLabel={categoriaPorCambiar.activa ? 'Desactivar' : 'Activar'} variant={categoriaPorCambiar.activa ? 'danger' : 'primary'}
            processing={procesando} error={errorEstado} onConfirm={() => void cambiarEstado()} onCancel={() => setCategoriaPorCambiar(null)}>
            <p><strong>{categoriaPorCambiar.nombre}</strong></p>
            <p>{categoriaPorCambiar.activa ? 'No podrá asignarse a productos nuevos. Los productos que ya la usan la conservan.' : 'Volverá a estar disponible para tus productos.'}</p>
        </ConfirmModal>}
    </>;
}
