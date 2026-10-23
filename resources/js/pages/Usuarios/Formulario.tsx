import axios from 'axios';
import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { useLocation, useNavigate, useOutletContext } from 'react-router-dom';
import { Button, Col, Row, Spinner } from 'react-bootstrap';
import { CheckboxListField, FormDialog, TextField } from '@/components/ui';
import { useSesion } from '@/lib/auth';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import type { Rol, Usuario } from '@/types/user';
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

export default function UsuarioFormulario({ usuario }: { usuario?: Usuario }) {
    const navigate = useNavigate();
    const location = useLocation();
    const { onGuardado, catalogo } = useOutletContext<UsuariosContexto>();
    const formId = useId();
    const { contexto } = useSesion();
    const formularioRef = useRef<HTMLFormElement>(null);
    const envioEnCurso = useRef(false);
    const [datos, setDatos] = useState<UsuarioForm>(() => ({
        name: usuario?.name ?? '',
        username: usuario?.username ?? '',
        email: usuario?.email ?? '',
        roles: usuario?.roles ?? ['cajero'],
        sucursales: usuario ? usuario.sucursales.map((sucursal) => sucursal.id) : contexto.sucursal ? [contexto.sucursal.id] : [],
        password: '',
        password_confirmation: '',
    }));
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [procesando, setProcesando] = useState(false);
    const esAdmin = datos.roles.includes('admin');
    const regreso = `/usuarios${location.search}`;
    const cerrar = () => navigate(regreso, { replace: true });
    const sucursales = [
        ...contexto.sucursales.map((sucursal) => ({ value: sucursal.id, label: sucursal.nombre })),
        ...(usuario?.sucursales ?? []).filter((sucursal) => !contexto.sucursales.some((activa) => activa.id === sucursal.id))
            .map((sucursal) => ({ value: sucursal.id, label: `${sucursal.nombre} (inactiva)` })),
    ];
    const tieneSucursalesInactivas = usuario?.sucursales.some((sucursal) => !sucursal.activa) ?? false;
    const sinSucursales = !esAdmin && sucursales.length === 0;

    useEffect(() => {
        if (Object.keys(errores).length > 0) {
            formularioRef.current?.querySelector<HTMLElement>('.form-control[aria-invalid="true"], .form-check-input[aria-invalid="true"]')?.focus();
        }
    }, [errores]);

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
            const payload = {
                ...datos,
                sucursales: esAdmin ? [] : datos.sucursales,
            };
            const response = usuario
                ? await api.put<{ data: Usuario }>(`/usuarios/${usuario.id}`, payload)
                : await api.post<{ data: Usuario }>('/usuarios', payload);
            onGuardado(response.data.data, usuario ? 'actualizado' : 'creado');
            cerrar();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            notificar(Object.keys(validacion).length > 0
                ? 'Revisa los campos marcados para guardar el usuario.'
                : axios.isAxiosError(exception) && exception.response?.status === 403
                    ? 'No tienes permiso para guardar usuarios.'
                    : axios.isAxiosError(exception) && exception.response?.status === 404
                        ? 'Este usuario ya no está disponible. Vuelve al listado.'
                    : mensajeDeError(exception), 'error');
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return (
        <FormDialog title={usuario ? 'Editar usuario' : 'Nuevo usuario'} onHide={cerrar} processing={procesando} actions={
            <>
                <Button type="button" variant="outline-secondary" disabled={procesando} onClick={cerrar}>Cancelar</Button>
                <Button type="submit" form={formId} disabled={procesando || catalogo.cargando || Boolean(catalogo.error) || sinSucursales}>
                    {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
                    {procesando ? 'Guardando…' : usuario ? 'Guardar cambios' : 'Crear usuario'}
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
                    <legend className="visually-hidden">Datos del usuario</legend>
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
                            hint={usuario?.es_usuario_actual && usuario.roles.includes('admin') ? 'Tu cuenta conserva el rol de Administrador para mantener tu acceso.' : 'Combina funciones o elige Administrador para dar todos los permisos.'}
                            disabled={catalogo.cargando || Boolean(catalogo.error) || Boolean(usuario?.es_usuario_actual && usuario.roles.includes('admin'))}
                        />
                        {esAdmin ? (
                            <p className="text-body-secondary mb-0">Los administradores tienen acceso a todas las sucursales del negocio.</p>
                        ) : sucursales.length === 0 ? (
                            <p className="text-warning-emphasis mb-0">No hay sucursales activas para asignar a este usuario.</p>
                        ) : (
                            <CheckboxListField
                                label="Sucursales"
                                options={sucursales}
                                value={datos.sucursales}
                                onChange={(sucursales) => setDatos({ ...datos, sucursales })}
                                error={errorDeCampo('sucursales')}
                                hint={tieneSucursalesInactivas ? 'Las sucursales inactivas conservan su asignación, pero no permiten trabajar. Puedes desmarcarlas para retirarlas.' : 'Elige al menos una sucursal.'}
                            />
                        )}
                    </section>
                    <section className="usuario-form__section" aria-labelledby="contrasena-usuario">
                        <h2 id="contrasena-usuario" className="h6 mb-2">Contraseña de ingreso</h2>
                        <Row className="g-2">
                            <Col md={6}>
                                <TextField label={usuario ? 'Nueva contraseña (opcional)' : 'Contraseña'} className="mb-0" type="password" autoComplete="new-password" required={!usuario} minLength={8}
                                    hint={usuario ? 'Déjala vacía para mantener la actual. Usa al menos 8 caracteres si la cambias.' : 'Usa al menos 8 caracteres.'} value={datos.password} error={errores.password}
                                    onChange={(event) => setDatos({ ...datos, password: event.target.value })} />
                            </Col>
                            <Col md={6}>
                                <TextField label="Confirmar contraseña" className="mb-0" type="password" autoComplete="new-password" required={!usuario || Boolean(datos.password)} minLength={8}
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
