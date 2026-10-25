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
import type { OpcionCatalogo, OpcionGrupoModificador, Producto } from '@/types/catalogo';

interface OpcionesProducto {
    categorias: OpcionCatalogo[];
    marcas: OpcionCatalogo[];
    grupos_modificadores: OpcionGrupoModificador[];
}

export interface ProductosContexto {
    opciones: ReturnType<typeof useConsultaApi<OpcionesProducto>>;
    onGuardado: (producto: Producto, operacion: 'creado' | 'actualizado') => void;
}

export default function ProductosIndex() {
    const location = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const [reiniciosBusqueda, setReiniciosBusqueda] = useState(0);
    const consulta = parametros.toString();
    const listado = useConsultaApi<Paginated<Producto>>('/productos'+(consulta ? '?'+consulta : ''));
    const opciones = useConsultaApi<OpcionesProducto>('/productos/opciones');
    const [productoPorCambiar, setProductoPorCambiar] = useState<Producto | null>(null);
    const [procesando, setProcesando] = useState(false);
    const [errorEstado, setErrorEstado] = useState('');
    const envioEnCurso = useRef(false);
    const buscar = parametros.get('buscar') ?? '';
    const categoria = parametros.get('categoria') ?? '';
    const marca = parametros.get('marca') ?? '';
    const estado = parametros.get('estado') ?? '';
    const tipo = parametros.get('tipo') ?? '';
    const hayFiltros = Boolean(buscar || categoria || marca || estado || tipo);
    const meta = listado.datos?.meta;
    const filas = listado.datos?.data ?? [];
    const categorias = opciones.datos?.categorias ?? [];
    const marcas = opciones.datos?.marcas ?? [];

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
        if (!productoPorCambiar || envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrorEstado('');
        try {
            const respuesta = await api.patch<{ data: Producto }>('/productos/'+productoPorCambiar.id+'/estado', { activo: !productoPorCambiar.activo });
            notificar('Producto '+(respuesta.data.data.activo ? 'activado' : 'desactivado')+' correctamente.');
            setProductoPorCambiar(null);
            listado.recargar();
        } catch (exception) {
            setErrorEstado(Object.values(erroresDeValidacion(exception)).join(' ') || mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    const columnas: Column<Producto>[] = [
        { header: 'Producto', mobile: 'title', cell: (producto) => <><strong>{producto.nombre}</strong>{producto.sku && <small className="d-block text-body-secondary">SKU: {producto.sku}</small>}</> },
        { header: 'Tipo', cell: (producto) => producto.se_vende && producto.es_insumo ? 'Venta e insumo' : producto.es_insumo ? 'Insumo' : 'Venta' },
        { header: 'Categoría', cell: (producto) => producto.categoria?.nombre ?? '—' },
        { header: 'Marca', cell: (producto) => producto.marca?.nombre ?? '—' },
        { header: 'Precio', cell: (producto) => producto.precio === null ? '—' : '$'+producto.precio.toLocaleString('es-CL') },
        { header: 'Estado', cell: (producto) => <Badge pill bg={producto.activo ? 'success-subtle' : 'secondary-subtle'} text={producto.activo ? 'success' : 'secondary'}>{producto.activo ? 'Activo' : 'Inactivo'}</Badge> },
        { header: 'Acciones', mobile: 'actions', cell: (producto) => <TableActions recordLabel={producto.nombre}
            primaryAction={{ label: 'Editar', icon: 'pencil', to: '/productos/'+producto.id+'/editar'+location.search }}
            actions={[{ label: producto.activo ? 'Desactivar' : 'Activar', icon: producto.activo ? 'toggle-off' : 'toggle-on', variant: producto.activo ? 'danger' : undefined,
                onClick: () => { setErrorEstado(''); setProductoPorCambiar(producto); }, }]} /> },
    ];
    let status: ReactNode;
    if (listado.error) {
        status = <div className="p-3" role="alert"><p>No se pudo cargar el listado de productos. {mensajeDeError(listado.error)}</p><Button variant="outline-secondary" onClick={listado.recargar}>Reintentar</Button></div>;
    } else if (listado.cargando) {
        status = <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando productos…</div>;
    }

    return <>
        <PageHeader title="Productos" subtitle="Administra los productos de tu catálogo."
            actions={<Link to={'/productos/nuevo'+location.search} className="btn btn-primary"><Icon name="plus-lg" className="me-2" />Nuevo producto</Link>} />
        <DataTable columns={columnas} rows={filas} rowKey={(producto) => producto.id} caption="Productos del negocio"
            status={status} busy={listado.cargando} meta={meta} emptyIcon="box-seam"
            onPageChange={(pagina) => setParametros((actuales) => { const nuevos = new URLSearchParams(actuales); nuevos.set('page', String(pagina)); return nuevos; })}
            emptyMessage={hayFiltros ? 'No encontramos productos con estos filtros.' : 'Todavía no tienes productos. Agrega el primero.'}
            emptyAction={hayFiltros ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todos los productos</Button>
                : <Link to="/productos/nuevo" className="btn btn-primary">Nuevo producto</Link>}
            toolbar={<div role="search" aria-label="Buscar y filtrar productos">
                <div className="table-toolbar align-items-end">
                    <div className="table-toolbar__search"><Form.Label htmlFor="productos-buscar">Buscar producto</Form.Label>
                        <SearchInput id="productos-buscar" key={reiniciosBusqueda} value={buscar} label="Buscar por nombre, SKU o código de barras" placeholder="Nombre, SKU o código de barras" maxLength={100} onChange={(valor) => filtrar('buscar', valor)} />
                    </div>
                    <div className="table-toolbar__filters"><Form.Group controlId="productos-categoria"><Form.Label>Categoría</Form.Label>
                        <Form.Select className="w-100" value={categoria} disabled={!opciones.datos} onChange={(event) => filtrar('categoria', event.target.value)}>
                            <option value="">Todas</option>{categorias.map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.nombre}{opcion.activa ? '' : ' (inactiva)'}</option>)}
                        </Form.Select>
                    </Form.Group></div>
                    <div className="table-toolbar__filters"><Form.Group controlId="productos-marca"><Form.Label>Marca</Form.Label>
                        <Form.Select className="w-100" value={marca} disabled={!opciones.datos} onChange={(event) => filtrar('marca', event.target.value)}>
                            <option value="">Todas</option>{marcas.map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.nombre}{opcion.activa ? '' : ' (inactiva)'}</option>)}
                        </Form.Select>
                    </Form.Group></div>
                    <div className="table-toolbar__filters"><Form.Group controlId="productos-tipo"><Form.Label>Tipo</Form.Label>
                        <Form.Select className="w-100" value={tipo} onChange={(event) => filtrar('tipo', event.target.value)}>
                            <option value="">Todos</option><option value="venta">Se venden</option><option value="insumo">Insumos</option>
                        </Form.Select>
                    </Form.Group></div>
                    <div className="table-toolbar__filters"><Form.Group controlId="productos-estado"><Form.Label>Estado</Form.Label>
                        <Form.Select className="w-100" value={estado} onChange={(event) => filtrar('estado', event.target.value)}>
                            <option value="">Todos</option><option value="activo">Activos</option><option value="inactivo">Inactivos</option>
                        </Form.Select>
                    </Form.Group></div>
                </div>
                {Boolean(opciones.error) && <div className="d-flex flex-wrap align-items-center gap-2 mt-3" role="alert">
                    <span>No se pudieron cargar las categorías y marcas.</span>
                    <Button variant="outline-secondary" size="sm" onClick={opciones.recargar}>Reintentar</Button>
                </div>}
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                    <p className="small text-body-secondary mb-0" role="status">{listado.cargando ? 'Buscando productos…' : listado.error ? 'Listado no disponible' : (meta?.total ?? 0)+' '+(meta?.total === 1 ? 'producto' : 'productos')+(hayFiltros ? ' con los filtros actuales' : ' en tu negocio')}</p>
                    {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                </div>
            </div>} />
        <Outlet context={{ opciones, onGuardado: (producto, operacion) => { notificar('Producto '+producto.nombre+' '+operacion+' correctamente.'); listado.recargar(); } } satisfies ProductosContexto} />
        {productoPorCambiar && <ConfirmModal title={productoPorCambiar.activo ? '¿Desactivar producto?' : '¿Activar producto?'}
            confirmLabel={productoPorCambiar.activo ? 'Desactivar' : 'Activar'} variant={productoPorCambiar.activo ? 'danger' : 'primary'}
            processing={procesando} error={errorEstado} onConfirm={() => void cambiarEstado()} onCancel={() => setProductoPorCambiar(null)}>
            <p><strong>{productoPorCambiar.nombre}</strong></p>
            <p>{productoPorCambiar.activo ? 'No estará disponible para nuevas operaciones. Se conserva su historial.' : 'Volverá a estar disponible en el catálogo.'}</p>
        </ConfirmModal>}
    </>;
}

