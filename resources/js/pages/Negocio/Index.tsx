import { useCallback, useId, useState } from 'react';
import { Badge, Button, Col, Form, Row, Spinner } from 'react-bootstrap';
import { Link } from 'react-router-dom';
import { PageHeader, TextField } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Negocio } from '@/types/negocio';
import Seccion, { useGuardadoSeccion } from './Seccion';

type CampoDatos = 'nombre' | 'rut' | 'razon_social' | 'giro' | 'direccion' | 'telefono' | 'email';

const CAMPOS_DATOS: CampoDatos[] = ['nombre', 'rut', 'razon_social', 'giro', 'direccion', 'telefono', 'email'];

function datosDe(negocio: Negocio): Record<CampoDatos, string> {
    return Object.fromEntries(CAMPOS_DATOS.map((campo) => [campo, negocio[campo] ?? ''])) as Record<CampoDatos, string>;
}

function SeccionDatos({ negocio }: { negocio: Negocio }) {
    const { actualizarNegocio } = useAuth();
    const [datos, setDatos] = useState(() => datosDe(negocio));
    const alGuardar = useCallback((guardado: Negocio) => {
        setDatos(datosDe(guardado));
        actualizarNegocio(guardado);
        notificar('Datos del negocio actualizados.');
    }, [actualizarNegocio]);
    const { guardar, procesando, errores, errorGeneral } = useGuardadoSeccion('/negocio', alGuardar);
    const campo = (nombre: CampoDatos) => ({
        value: datos[nombre],
        error: errores[nombre],
        onChange: (event: { target: { value: string } }) => setDatos((actual) => ({ ...actual, [nombre]: event.target.value })),
    });

    return (
        <Seccion title="Datos del negocio" description="Identifican a tu negocio en la aplicación, tickets y documentos."
            submitLabel="Guardar datos" processing={procesando} errores={errores} errorGeneral={errorGeneral} onSubmit={() => void guardar(datos)}>
            <Row>
                <Col md={6}>
                    <TextField label="Código de negocio" value={negocio.codigo} readOnly disabled hint="Tus usuarios lo usan para iniciar sesión. No se puede cambiar." />
                </Col>
                <Col md={6}><TextField label="Nombre comercial" autoComplete="organization" required maxLength={255} {...campo('nombre')} /></Col>
                <Col md={6}><TextField label="RUT (opcional)" placeholder="12.345.678-5" autoComplete="off" maxLength={12} hint="Se guarda sin puntos y con guion." {...campo('rut')} /></Col>
                <Col md={6}><TextField label="Razón social (opcional)" autoComplete="off" maxLength={255} {...campo('razon_social')} /></Col>
                <Col md={6}><TextField label="Giro (opcional)" autoComplete="off" maxLength={255} {...campo('giro')} /></Col>
                <Col md={6}><TextField label="Dirección (opcional)" autoComplete="street-address" maxLength={255} {...campo('direccion')} /></Col>
                <Col md={6}><TextField label="Teléfono (opcional)" type="tel" autoComplete="tel" maxLength={30} {...campo('telefono')} /></Col>
                <Col md={6}><TextField label="Correo electrónico (opcional)" type="email" autoComplete="email" maxLength={255} {...campo('email')} /></Col>
            </Row>
        </Seccion>
    );
}

function SeccionModulos({ negocio }: { negocio: Negocio }) {
    const { actualizarNegocio } = useAuth();
    const grupoId = useId();
    const [activos, setActivos] = useState(() => negocio.modulos.filter((modulo) => modulo.activo).map((modulo) => modulo.value));
    const alGuardar = useCallback((guardado: Negocio) => {
        setActivos(guardado.modulos.filter((modulo) => modulo.activo).map((modulo) => modulo.value));
        actualizarNegocio(guardado);
        notificar('Módulos actualizados.');
    }, [actualizarNegocio]);
    const { guardar, procesando, errores, errorGeneral } = useGuardadoSeccion('/negocio/modulos', alGuardar);
    const errorModulos = Object.entries(errores).filter(([campo]) => campo === 'modulos' || campo.startsWith('modulos.')).map(([, mensaje]) => mensaje)[0];
    const noIncluidos = negocio.modulos.filter((modulo) => !modulo.incluido);

    return (
        <Seccion title="Plan y módulos" description="Activa solo lo que usa tu negocio. Desactivar un módulo lo oculta, sin borrar sus datos."
            submitLabel="Guardar módulos" processing={procesando} errores={errores} errorGeneral={errorGeneral} onSubmit={() => void guardar({ modulos: activos })}>
            <Row className="g-3 mb-3">
                <Col md={5}>
                    <div className="border rounded p-3 h-100">
                        <div className="small text-body-secondary">Tu plan</div>
                        <div className="h5 mb-1">{negocio.plan.label}</div>
                        <p className="small text-body-secondary mb-2">{negocio.plan.descripcion}</p>
                        <Link to="/plan" className="small">Ver cobro o cambiar de plan en Mi plan</Link>
                    </div>
                </Col>
                <Col md={7}>
                    <fieldset aria-describedby={errorModulos ? grupoId+'-error' : undefined}>
                        <legend className="form-label fs-6 mb-2">Módulos</legend>
                        {negocio.modulos.map((modulo) => (
                            <Form.Check key={modulo.value} type="switch" id={grupoId+'-'+modulo.value} className="mb-3">
                                <Form.Check.Input type="checkbox" disabled={!modulo.incluido} isInvalid={Boolean(errorModulos)} aria-invalid={errorModulos ? true : undefined}
                                    aria-describedby={grupoId+'-'+modulo.value+'-ayuda'} checked={modulo.incluido && activos.includes(modulo.value)}
                                    onChange={(event) => setActivos((actual) => event.target.checked ? [...actual, modulo.value] : actual.filter((valor) => valor !== modulo.value))} />
                                <Form.Check.Label>{modulo.label}</Form.Check.Label>
                                {!modulo.incluido && <Badge bg="secondary-subtle" text="secondary" className="fw-normal ms-2">No incluido en tu plan</Badge>}
                                <div id={grupoId+'-'+modulo.value+'-ayuda'} className="small text-body-secondary">{modulo.descripcion}</div>
                            </Form.Check>
                        ))}
                        {errorModulos && <div id={grupoId+'-error'} className="invalid-feedback d-block mb-3">{errorModulos}</div>}
                    </fieldset>
                    {noIncluidos.length > 0 && <p className="small text-body-secondary mb-0">
                        El plan {negocio.plan.label} no incluye {noIncluidos.map((modulo) => modulo.label.toLowerCase()).join(' ni ')}. Para usarlos, deberás cambiar de plan.
                    </p>}
                </Col>
            </Row>
        </Seccion>
    );
}

function SeccionPreferencias({ negocio }: { negocio: Negocio }) {
    const cierreId = useId();
    const [cierreCiego, setCierreCiego] = useState(negocio.cierre_ciego);
    const [descuento, setDescuento] = useState(String(negocio.descuento_maximo_cajero));
    const alGuardar = useCallback((guardado: Negocio) => {
        setCierreCiego(guardado.cierre_ciego);
        setDescuento(String(guardado.descuento_maximo_cajero));
        notificar('Preferencias actualizadas.');
    }, []);
    const { guardar, procesando, errores, errorGeneral } = useGuardadoSeccion('/negocio/preferencias', alGuardar);

    return (
        <Seccion title="Preferencias de operación" description="Cómo se cierran los turnos de caja y cuánto pueden descontar los cajeros. Se aplicarán en los turnos de caja y las ventas."
            submitLabel="Guardar preferencias" processing={procesando} errores={errores} errorGeneral={errorGeneral}
            onSubmit={() => void guardar({ cierre_ciego: cierreCiego, descuento_maximo_cajero: descuento.trim() === '' ? null : Number(descuento) })}>
            <Form.Check type="switch" id={cierreId} className="mb-3">
                <Form.Check.Input type="checkbox" aria-describedby={cierreId+'-ayuda'} checked={cierreCiego} onChange={(event) => setCierreCiego(event.target.checked)} />
                <Form.Check.Label>Cierre ciego de caja</Form.Check.Label>
                <div id={cierreId+'-ayuda'} className="small text-body-secondary">El cajero cuenta el efectivo sin ver cuánto debería haber. Solo los administradores ven el monto esperado y la diferencia.</div>
            </Form.Check>
            <TextField label="Descuento máximo del cajero (%)" type="number" inputMode="numeric" min={0} max={100} step={1} required style={{ maxWidth: '10rem' }}
                hint="Entre 0 % y 100 % del total de la venta. Los administradores descuentan sin límite. Con 0 %, los cajeros no pueden descontar."
                value={descuento} error={errores.descuento_maximo_cajero ?? errores.cierre_ciego} onChange={(event) => setDescuento(event.target.value)} />
        </Seccion>
    );
}

export default function NegocioIndex() {
    const consulta = useConsultaApi<{ data: Negocio }>('/negocio');
    const negocio = consulta.datos?.data;

    return (
        <>
            <PageHeader title="Mi negocio" subtitle="Datos, plan y preferencias de operación de tu negocio." />
            {consulta.error ? (
                <div role="alert"><p>No se pudo cargar la configuración del negocio. {mensajeDeError(consulta.error)}</p><Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button></div>
            ) : !negocio ? (
                <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando configuración…</div>
            ) : (
                <>
                    <SeccionDatos negocio={negocio} />
                    <SeccionModulos negocio={negocio} />
                    <SeccionPreferencias negocio={negocio} />
                </>
            )}
        </>
    );
}
