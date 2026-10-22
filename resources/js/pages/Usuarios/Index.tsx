import axios from 'axios';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Link, Outlet, useLocation, useSearchParams } from 'react-router-dom';
import { Badge, Button, Form, Spinner } from 'react-bootstrap';
import { DataTable, Icon, PageHeader, SearchInput } from '@/components/ui';
import type { Column } from '@/components/ui';
import { erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { notificar } from '@/lib/notificaciones';
import type { Paginated } from '@/types/api';
import type { OpcionRol, Usuario } from '@/types/user';

export interface UsuariosContexto {
    onCreado: (usuario: Usuario) => void;
}

export default function UsuariosIndex() {
    const location = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const [reiniciosBusqueda, setReiniciosBusqueda] = useState(0);
    const consulta = parametros.toString();
    const listado = useConsultaApi<Paginated<Usuario>>(`/usuarios${consulta ? `?${consulta}` : ''}`);
    const catalogo = useConsultaApi<{ data: OpcionRol[] }>('/roles');
    const buscar = parametros.get('buscar') ?? '';
    const rol = parametros.get('rol') ?? '';
    const estado = parametros.get('estado') ?? '';
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
                subtitle="Personas que pueden ingresar a tu negocio."
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
                emptyMessage="No encontramos usuarios con estos filtros."
                toolbar={
                    <div className="table-toolbar" role="search" aria-label="Buscar y filtrar usuarios">
                        <div className="table-toolbar__search">
                            <SearchInput
                                key={reiniciosBusqueda}
                                value={buscar}
                                onChange={(valor) => filtrar('buscar', valor)}
                                label="Buscar usuarios por nombre, usuario o correo"
                                placeholder="Nombre, usuario o correo"
                                maxLength={100}
                            />
                        </div>
                        <div className="table-toolbar__filters">
                            <Form.Select aria-label="Filtrar por rol" value={rol} onChange={(event) => filtrar('rol', event.target.value)}>
                                <option value="">Todos los roles</option>
                                {roles.map((opcion) => <option key={opcion.value} value={opcion.value}>{opcion.label}</option>)}
                            </Form.Select>
                            <Form.Select aria-label="Filtrar por estado" value={estado} onChange={(event) => filtrar('estado', event.target.value)}>
                                <option value="">Todos los estados</option>
                                <option value="activo">Activos</option>
                                <option value="inactivo">Inactivos</option>
                            </Form.Select>
                            {consulta && <Button variant="outline-secondary" onClick={limpiarFiltros}>Limpiar filtros</Button>}
                        </div>
                    </div>
                }
            />
            <Outlet context={{ onCreado: (usuario) => { notificar(`Usuario ${usuario.name} creado correctamente.`); listado.recargar(); } } satisfies UsuariosContexto} />
        </>
    );
}
