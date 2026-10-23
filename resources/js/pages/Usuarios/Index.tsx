import axios from 'axios';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Link, Outlet, useLocation, useSearchParams } from 'react-router-dom';
import { Badge, Button, Form, Spinner } from 'react-bootstrap';
import { ConfirmModal, DataTable, Icon, PageHeader, SearchInput } from '@/components/ui';
import type { Column } from '@/components/ui';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { useAuth } from '@/lib/auth';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { notificar } from '@/lib/notificaciones';
import type { Paginated } from '@/types/api';
import type { OpcionRol, Usuario } from '@/types/user';

export interface UsuariosContexto {
    onGuardado: (usuario: Usuario, operacion: 'creado' | 'actualizado') => void;
    catalogo: ReturnType<typeof useConsultaApi<{ data: OpcionRol[] }>>;
}

export default function UsuariosIndex() {
    const location = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const [reiniciosBusqueda, setReiniciosBusqueda] = useState(0);
    const consulta = parametros.toString();
    const listado = useConsultaApi<Paginated<Usuario>>(`/usuarios${consulta ? `?${consulta}` : ''}`);
    const catalogo = useConsultaApi<{ data: OpcionRol[] }>('/roles');
    const { actualizarUsuario } = useAuth();
    const [usuarioPorCambiar, setUsuarioPorCambiar] = useState<Usuario | null>(null);
    const [cambiandoEstado, setCambiandoEstado] = useState(false);
    const [errorEstado, setErrorEstado] = useState('');
    const cambioEnCurso = useRef(false);
    const buscar = parametros.get('buscar') ?? '';
    const rol = parametros.get('rol') ?? '';
    const estado = parametros.get('estado') ?? '';
    const hayFiltros = Boolean(buscar || rol || estado);
    const roles = catalogo.datos?.data ?? [];
    const meta = listado.datos?.meta;
    const error = listado.error ?? catalogo.error;
    const cargando = listado.cargando || catalogo.cargando;

    useEffect(() => {
        if (!error) {
            return;
        }

        const esValidacion = axios.isAxiosError(error) && error.response?.status === 422;
        const mensaje = axios.isAxiosError(error) && error.response?.status === 403
            ? 'No tienes permiso para consultar usuarios.'
            : esValidacion
                ? `Revisa los filtros de búsqueda. ${Object.values(erroresDeValidacion(error)).join(' ')}`
                : mensajeDeError(error);

        notificar(mensaje, 'error');
    }, [error]);

    useEffect(() => {
        if (meta && meta.current_page > meta.last_page) {
            setParametros((actuales) => {
                const nuevos = new URLSearchParams(actuales);
                nuevos.set('page', String(meta.last_page));

                return nuevos;
            }, { replace: true });
        }
    }, [meta, setParametros]);

    const filtrar = (campo: string, valor: string) => {
        setParametros((actuales) => {
            const nuevos = new URLSearchParams(actuales);
            if (valor) {
                nuevos.set(campo, valor);
            } else {
                nuevos.delete(campo);
            }
            nuevos.delete('page');

            return nuevos;
        }, { replace: campo === 'buscar' });
    };

    const limpiarFiltros = () => {
        setReiniciosBusqueda((actual) => actual + 1);
        setParametros({});
    };

    const cambiarPagina = (page: number) => {
        setParametros((actuales) => {
            const nuevos = new URLSearchParams(actuales);
            nuevos.set('page', String(page));

            return nuevos;
        });
    };

    const guardarEstado = async () => {
        if (!usuarioPorCambiar || cambioEnCurso.current) {
            return;
        }

        cambioEnCurso.current = true;
        setCambiandoEstado(true);
        setErrorEstado('');

        try {
            const response = await api.patch<{ data: Usuario }>(`/usuarios/${usuarioPorCambiar.id}/estado`, { activo: !usuarioPorCambiar.activo });
            notificar(`Usuario ${response.data.data.activo ? 'activado' : 'desactivado'} correctamente.`);
            setUsuarioPorCambiar(null);
            listado.recargar();
        } catch (exception) {
            const mensaje = Object.values(erroresDeValidacion(exception)).join(' ')
                || (axios.isAxiosError(exception) && exception.response?.status === 403
                    ? 'No tienes permiso para cambiar el estado de usuarios.'
                    : axios.isAxiosError(exception) && exception.response?.status === 404
                        ? 'Este usuario ya no está disponible. Actualiza el listado.'
                        : mensajeDeError(exception));
            setErrorEstado(mensaje);
        } finally {
            cambioEnCurso.current = false;
            setCambiandoEstado(false);
        }
    };

    const columnas: Column<Usuario>[] = [
        {
            header: 'Nombre',
            mobile: 'title',
            cell: (usuario) => (
                <>
                    {usuario.name}
                    {usuario.es_usuario_actual && <Badge bg="light" text="dark" className="ms-2 fw-normal">Tú</Badge>}
                    {usuario.email && <div className="small text-body-secondary fw-normal">{usuario.email}</div>}
                </>
            ),
        },
        { header: 'Usuario', cell: (usuario) => <code className="text-body">{usuario.username}</code> },
        {
            header: 'Roles',
            cell: (usuario) => (
                <div className="d-flex flex-wrap justify-content-end justify-content-md-start gap-1">
                    {usuario.roles.map((asignado) => (
                        <Badge key={asignado} pill bg={asignado === 'admin' ? 'primary' : 'info'} text={asignado === 'admin' ? undefined : 'dark'}>
                            {roles.find((opcion) => opcion.value === asignado)?.label ?? asignado}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            header: 'Sucursales',
            cell: (usuario) => usuario.roles.includes('admin')
                ? <span className="text-body-secondary">Todas las sucursales</span>
                : usuario.sucursales.map((sucursal) => sucursal.nombre).join(', ') || 'Sin sucursales asignadas',
        },
        {
            header: 'Estado',
            cell: (usuario) => (
                <Badge pill bg={usuario.activo ? 'success-subtle' : 'secondary-subtle'} text={usuario.activo ? 'success' : 'secondary'}>
                    {usuario.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            header: 'Acciones',
            mobile: 'actions',
            cell: (usuario) => (
                <div className="usuario-acciones">
                    <Link to={`/usuarios/${usuario.id}/editar${location.search}`} className="btn btn-outline-secondary" aria-label={`Editar a ${usuario.name}`}>
                        <Icon name="pencil" className="me-2" />Editar
                    </Link>
                    <Button variant="outline-secondary" disabled={usuario.es_usuario_actual}
                        aria-label={`${usuario.activo ? 'Desactivar' : 'Activar'} a ${usuario.name}`}
                        title={usuario.es_usuario_actual ? 'No puedes desactivar tu propio usuario.' : undefined}
                        onClick={() => { setErrorEstado(''); setUsuarioPorCambiar(usuario); }}>
                        <Icon name={usuario.activo ? 'person-dash' : 'person-check'} className="me-2" />
                        {usuario.activo ? 'Desactivar' : 'Activar'}
                    </Button>
                </div>
            ),
        },
    ];

    let status: ReactNode;

    if (error) {
        status = (
            <div className="p-3">
                <p>No se pudo cargar el listado de usuarios.</p>
                <Button variant="outline-secondary" onClick={() => { listado.recargar(); catalogo.recargar(); }}>Reintentar</Button>
            </div>
        );
    } else if (cargando) {
        status = (
            <div className="py-5 text-center" role="status">
                <Spinner animation="border" size="sm" aria-hidden="true" className="me-2" />
                Cargando usuarios…
            </div>
        );
    }

    return (
        <>
            <PageHeader
                title="Usuarios"
                subtitle="Consulta quién puede ingresar al negocio, qué funciones tiene y en qué sucursales trabaja."
                actions={<Link to={`/usuarios/nuevo${location.search}`} className="btn btn-primary"><Icon name="plus-lg" className="me-2" />Nuevo usuario</Link>}
            />
            <DataTable
                columns={columnas}
                rows={listado.datos?.data ?? []}
                rowKey={(usuario) => usuario.id}
                caption="Usuarios del negocio"
                status={status}
                busy={cargando}
                meta={meta}
                onPageChange={cambiarPagina}
                emptyMessage={hayFiltros ? 'No encontramos usuarios con estos filtros. Prueba con otro nombre o amplía la búsqueda.' : 'Aún no hay usuarios para mostrar. Agrega a una persona de tu equipo para darle acceso.'}
                emptyAction={hayFiltros
                    ? <Button variant="outline-secondary" onClick={limpiarFiltros}>Ver todos los usuarios</Button>
                    : <Link to={`/usuarios/nuevo${location.search}`} className="btn btn-primary">Nuevo usuario</Link>}
                toolbar={
                    <div role="search" aria-label="Buscar y filtrar usuarios">
                        <div className="table-toolbar align-items-end">
                            <div className="table-toolbar__search">
                                <Form.Label htmlFor="usuarios-buscar">Buscar usuario</Form.Label>
                                <SearchInput
                                    id="usuarios-buscar"
                                    key={reiniciosBusqueda}
                                    value={buscar}
                                    onChange={(valor) => filtrar('buscar', valor)}
                                    label="Buscar usuario por nombre, usuario o correo"
                                    placeholder="Nombre, usuario o correo"
                                    maxLength={100}
                                />
                            </div>
                            <div className="table-toolbar__filters">
                                <Form.Group controlId="usuarios-filtro-rol" className="col-12 col-sm-auto">
                                    <Form.Label>Rol</Form.Label>
                                    <Form.Select className="w-100" value={rol} onChange={(event) => filtrar('rol', event.target.value)}>
                                        <option value="">Todos los roles</option>
                                        {roles.map((opcion) => <option key={opcion.value} value={opcion.value}>{opcion.label}</option>)}
                                    </Form.Select>
                                </Form.Group>
                                <Form.Group controlId="usuarios-filtro-estado" className="col-12 col-sm-auto">
                                    <Form.Label>Estado</Form.Label>
                                    <Form.Select className="w-100" value={estado} onChange={(event) => filtrar('estado', event.target.value)}>
                                        <option value="">Todos los estados</option>
                                        <option value="activo">Activos</option>
                                        <option value="inactivo">Inactivos</option>
                                    </Form.Select>
                                </Form.Group>
                            </div>
                        </div>
                        <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                            <p className="small text-body-secondary mb-0" role="status">
                                {cargando ? 'Buscando usuarios…' : error ? 'Listado no disponible' : `${meta?.total ?? 0} ${meta?.total === 1 ? 'usuario' : 'usuarios'}${hayFiltros ? ' con los filtros actuales' : ' en tu negocio'}`}
                            </p>
                            {hayFiltros && <Button variant="link" className="p-0" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                        </div>
                    </div>
                }
            />
            <Outlet context={{ catalogo, onGuardado: (usuario, operacion) => {
                actualizarUsuario(usuario);
                notificar(`Usuario ${usuario.name} ${operacion} correctamente.`);
                listado.recargar();
            } } satisfies UsuariosContexto} />
            {usuarioPorCambiar && (
                <ConfirmModal title={usuarioPorCambiar.activo ? '¿Desactivar usuario?' : '¿Activar usuario?'}
                    confirmLabel={usuarioPorCambiar.activo ? 'Desactivar' : 'Activar'}
                    variant={usuarioPorCambiar.activo ? 'danger' : 'primary'} processing={cambiandoEstado} error={errorEstado}
                    onConfirm={() => void guardarEstado()} onCancel={() => setUsuarioPorCambiar(null)}>
                    <p className="mb-0"><strong>{usuarioPorCambiar.name}</strong> {usuarioPorCambiar.activo
                        ? 'perderá el acceso al sistema, incluida su sesión actual. Su historial se conservará.'
                        : 'podrá volver a ingresar al sistema con sus roles y sucursales asignados.'}</p>
                </ConfirmModal>
            )}
        </>
    );
}
