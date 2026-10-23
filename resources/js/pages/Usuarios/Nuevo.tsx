import axios from 'axios';
import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { useLocation, useNavigate, useOutletContext } from 'react-router-dom';
import { Button, Col, Row, Spinner } from 'react-bootstrap';
import { CheckboxListField, FormDialog, TextField } from '@/components/ui';
import { useSesion } from '@/lib/auth';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { notificar } from '@/lib/notificaciones';
import type { OpcionRol, Rol, Usuario } from '@/types/user';
import type { UsuariosContexto } from './Index';

interface UsuarioForm {
    name: string;
    username: string;
    email: string;
    roles: Rol[];
    sucursales: string[];
    password: string;
    password_confirmation: string;
}

export default function NuevoUsuario() {
    const navigate = useNavigate();
    const location = useLocation();
    const { onCreado } = useOutletContext<UsuariosContexto>();
    const formId = useId();
    const { contexto } = useSesion();
    const catalogo = useConsultaApi<{ data: OpcionRol[] }>('/roles');
    const formularioRef = useRef<HTMLFormElement>(null);
    const envioEnCurso = useRef(false);
    const [datos, setDatos] = useState<UsuarioForm>(() => ({
        name: '',
        username: '',
        email: '',
        roles: ['cajero'],
        sucursales: contexto.sucursal ? [contexto.sucursal.id] : [],
        password: '',
        password_confirmation: '',
    }));
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [procesando, setProcesando] = useState(false);
    const esAdmin = datos.roles.includes('admin');
    const sinSucursales = !esAdmin && contexto.sucursales.length === 0;
    const regreso = `/usuarios${location.search}`;
    const cerrar = () => navigate(regreso, { replace: true });

    useEffect(() => {
        if (Object.keys(errores).length > 0) {
            formularioRef.current?.querySelector<HTMLElement>('.form-control[aria-invalid="true"], .form-check-input[aria-invalid="true"]')?.focus();
        }
    }, [errores]);

    useEffect(() => {
        if (catalogo.error) {
            notificar(`No se pudieron cargar los roles. ${mensajeDeError(catalogo.error)}`, 'error');
        }
    }, [catalogo.error]);

    const errorDeCampo = (campo: string) => errores[campo]
        ?? Object.entries(errores).find(([key]) => key.startsWith(`${campo}.`))?.[1];

    const seleccionarRoles = (seleccion: string[]) => {
        const eligeAdmin = seleccion.includes('admin') && !esAdmin;
        const roles: Rol[] = eligeAdmin
            ? ['admin']
            : (catalogo.datos?.data ?? []).filter((opcion) => opcion.value !== 'admin' && seleccion.includes(opcion.value)).map((opcion) => opcion.value);

        setDatos((actual) => ({ ...actual, roles }));
    };

    const guardar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (envioEnCurso.current) {
            return;
        }

        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});

        try {
            const response = await api.post<{ data: Usuario }>('/usuarios', {
                ...datos,
                sucursales: esAdmin ? [] : datos.sucursales,
            });
            const usuario = response.data.data;
            onCreado(usuario);
            cerrar();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            notificar(Object.keys(validacion).length > 0
                ? 'Revisa los campos marcados para crear el usuario.'
                : axios.isAxiosError(exception) && exception.response?.status === 403
                    ? 'No tienes permiso para crear usuarios.'
                    : mensajeDeError(exception), 'error');
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return (
        <FormDialog title="Nuevo usuario" onHide={cerrar} processing={procesando} actions={
            <>
                <Button type="button" variant="outline-secondary" disabled={procesando} onClick={cerrar}>Cancelar</Button>
                <Button type="submit" form={formId} disabled={procesando || catalogo.cargando || Boolean(catalogo.error) || sinSucursales}>
                    {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
                    {procesando ? 'Creando usuario…' : 'Crear usuario'}
                </Button>
            </>
        }>
            {catalogo.error ? (
                <div className="mb-3">
                    <p>No se pudieron cargar los roles.</p>
                    <Button variant="outline-danger" onClick={catalogo.recargar}>Reintentar</Button>
                </div>
            ) : null}
            <form ref={formularioRef} id={formId} className="usuario-form" onSubmit={(event) => void guardar(event)} noValidate aria-busy={procesando}>
                <fieldset disabled={procesando}>
                    <legend className="visually-hidden">Datos del nuevo usuario</legend>
                    <section className="usuario-form__section" aria-labelledby="datos-usuario">
                        <h2 id="datos-usuario" className="h6 mb-2">Datos personales</h2>
                            <Row className="g-2">
                                <Col md={6}>
                                    <TextField label="Nombre" className="mb-0" value={datos.name} required maxLength={255} autoComplete="off" error={errores.name}
                                        onChange={(event) => setDatos({ ...datos, name: event.target.value })} />
                                </Col>
                                <Col md={6}>
                                    <TextField label="Usuario" className="mb-0" value={datos.username} required minLength={3} maxLength={30} autoComplete="off" autoCapitalize="none" autoCorrect="off" spellCheck={false}
                                        hint="3–30 caracteres: letras, números, punto o guion bajo." error={errores.username}
                                        onChange={(event) => setDatos({ ...datos, username: event.target.value })} />
                                </Col>
                                <Col xs={12}>
                                    <TextField label="Correo electrónico (opcional)" type="email" inputMode="email" autoComplete="off" autoCapitalize="none" maxLength={255}
                                        value={datos.email} error={errores.email} className="mb-0"
                                        hint="Permite recuperar la contraseña si la olvida."
                                        onChange={(event) => setDatos({ ...datos, email: event.target.value })} />
                                </Col>
                            </Row>
                    </section>
                    <section className="usuario-form__section" aria-labelledby="acceso-usuario">
                        <h2 id="acceso-usuario" className="h6 mb-2">Acceso y sucursales</h2>
                            {catalogo.cargando && <p role="status"><Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />Cargando roles…</p>}
                            <CheckboxListField
                                label="Roles"
                                options={catalogo.datos?.data ?? []}
                                value={datos.roles}
                                onChange={seleccionarRoles}
                                error={errorDeCampo('roles')}
                                hint="Combina funciones o elige Administrador para dar todos los permisos."
                                disabled={catalogo.cargando || Boolean(catalogo.error)}
                            />
                            {esAdmin ? (
                                <p className="text-body-secondary mb-0">Los administradores tienen acceso a todas las sucursales del negocio.</p>
                            ) : sinSucursales ? (
                                <p className="text-warning-emphasis mb-0">No hay sucursales activas para asignar a este usuario.</p>
                            ) : (
                                <CheckboxListField
                                    label="Sucursales"
                                    options={contexto.sucursales.map((sucursal) => ({ value: sucursal.id, label: sucursal.nombre }))}
                                    value={datos.sucursales}
                                    onChange={(sucursales) => setDatos({ ...datos, sucursales })}
                                    error={errorDeCampo('sucursales')}
                                    hint="Elige al menos una sucursal."
                                />
                            )}
                    </section>
                    <section className="usuario-form__section" aria-labelledby="contrasena-usuario">
                        <h2 id="contrasena-usuario" className="h6 mb-2">Contraseña de ingreso</h2>
                            <Row className="g-2">
                                <Col md={6}>
                                    <TextField label="Contraseña" className="mb-0" type="password" autoComplete="new-password" required minLength={8}
                                        hint="Usa al menos 8 caracteres." value={datos.password} error={errores.password}
                                        onChange={(event) => setDatos({ ...datos, password: event.target.value })} />
                                </Col>
                                <Col md={6}>
                                    <TextField label="Confirmar contraseña" className="mb-0" type="password" autoComplete="new-password" required minLength={8}
                                        value={datos.password_confirmation} error={errores.password_confirmation}
                                        onChange={(event) => setDatos({ ...datos, password_confirmation: event.target.value })} />
                                </Col>
                            </Row>
                        {contexto.negocio && <p className="small text-body-secondary mt-2 mb-0">Para ingresar: código <strong>{contexto.negocio.codigo}</strong>, usuario y contraseña.</p>}
                    </section>
                </fieldset>
            </form>
        </FormDialog>
    );
}
