import { useEffect, useId, useRef, useState } from 'react';
import { Alert, Badge, Button, Card, Col, Form, Row, Spinner } from 'react-bootstrap';
import { ConfirmModal, Icon, PageHeader } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { formatoUf } from '@/pages/Cajas/AvisoCobro';
import type { CotizacionPlan, MiPlan, OpcionPlan } from '@/types/plan';

const precioMensual = (precio: string | null) => precio === null ? 'Precio por confirmar' : formatoUf(precio)+' UF/mes + IVA';

function CambiarPlan({ opcion, actual, onCancel, onCambiado }: { opcion: OpcionPlan; actual: string; onCancel: () => void; onCambiado: () => void }) {
    const aceptacionId = useId();
    const cotizacion = useConsultaApi<{ data: CotizacionPlan }>('/plan/cotizacion?plan='+opcion.value);
    const cambio = cotizacion.datos?.data;
    const [aceptado, setAceptado] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [errorAceptacion, setErrorAceptacion] = useState('');
    const envioEnCurso = useRef(false);

    useEffect(() => setAceptado(false), [cambio?.cotizacion]);

    const confirmar = async () => {
        if (envioEnCurso.current || !cambio) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setError('');
        setErrorAceptacion('');
        try {
            await api.put('/plan', { plan: opcion.value, acepto_cobro: aceptado, cotizacion: cambio.cotizacion });
            onCambiado();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrorAceptacion(validacion.acepto_cobro ?? '');
            setError(validacion.cotizacion ?? validacion.tarifa ?? validacion.plan ?? (Object.keys(validacion).length ? '' : mensajeDeError(exception)));
            if (validacion.cotizacion || validacion.tarifa) { setAceptado(false); cotizacion.recargar(); }
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return (
        <ConfirmModal title={'¿Cambiar al plan '+opcion.label+'?'} confirmLabel="Cambiar de plan" processing={procesando} error={error}
            confirmDisabled={!cambio || cotizacion.cargando || cambio.precio_uf === null || !aceptado} onConfirm={() => void confirmar()} onCancel={onCancel}>
            <p>Dejarás el plan {actual} y pasarás a <strong>{opcion.label}</strong>: {opcion.descripcion.toLowerCase()}</p>
            {cotizacion.cargando ? <p role="status">Consultando condiciones del plan…</p>
                : cotizacion.error ? <div role="alert"><p>No se pudieron consultar las condiciones.</p><Button variant="outline-secondary" onClick={cotizacion.recargar}>Reintentar</Button></div>
                : cambio && <>
                    {cambio.precio_uf === null ? <Alert variant="warning" className="small">El precio de este plan no está configurado. Contacta a soporte para cambiarte.</Alert>
                        : <Alert variant="warning" className="small">
                            <p className="mb-1">Nuevo precio: <strong>{formatoUf(cambio.precio_uf)} UF/mes + IVA</strong>, desde hoy.</p>
                            <p className="mb-0">Tus sucursales y cajas adicionales conservan su precio contratado.</p>
                        </Alert>}
                    {cambio.modulos_agregados.length > 0 && <p className="small mb-2"><Icon name="plus-circle" className="me-1 text-success" />Se activan: {cambio.modulos_agregados.map((modulo) => modulo.label).join(', ')}.</p>}
                    {cambio.modulos_retirados.length > 0 && <p className="small mb-2"><Icon name="dash-circle" className="me-1 text-danger" />Dejan de estar disponibles: {cambio.modulos_retirados.map((modulo) => modulo.label).join(', ')}. Sus datos se conservan.</p>}
                    {cambio.sin_retorno && <p className="small mb-2"><Icon name="exclamation-triangle" className="me-1 text-warning" />El plan {actual} ya no se ofrece: no podrás volver a él.</p>}
                    {cambio.precio_uf !== null && <Form.Group className="mt-3">
                        <Form.Check id={aceptacionId} className="contract-acceptance" checked={aceptado} disabled={procesando} isInvalid={Boolean(errorAceptacion)}
                            label="Acepto el nuevo cobro mensual indicado." aria-invalid={errorAceptacion ? true : undefined}
                            aria-describedby={errorAceptacion ? aceptacionId+'-error' : undefined} onChange={(event) => setAceptado(event.target.checked)} />
                        {errorAceptacion && <div id={aceptacionId+'-error'} className="invalid-feedback d-block">{errorAceptacion}</div>}
                    </Form.Group>}
                </>}
        </ConfirmModal>
    );
}

export default function PlanIndex() {
    const { refresh } = useAuth();
    const consulta = useConsultaApi<{ data: MiPlan }>('/plan');
    const datos = consulta.datos?.data;
    const [opcionElegida, setOpcionElegida] = useState<OpcionPlan | null>(null);

    const alCambiar = () => {
        notificar('Plan cambiado a '+opcionElegida?.label+'.');
        setOpcionElegida(null);
        consulta.recargar();
        void refresh().catch(() => {});
    };

    return (
        <>
            <PageHeader title="Mi plan" subtitle="Qué incluye tu plan y cuánto pagas cada mes." />
            {consulta.error ? (
                <div role="alert"><p>No se pudo cargar tu plan. {mensajeDeError(consulta.error)}</p><Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button></div>
            ) : !datos ? (
                <div className="py-5 text-center" role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando plan…</div>
            ) : (
                <>
                    <Row className="g-4 mb-4">
                        <Col lg={6}>
                            <Card as="section" className="h-100" aria-labelledby="plan-actual">
                                <Card.Body>
                                    <div className="small text-body-secondary">Tu plan</div>
                                    <h2 id="plan-actual" className="h4 mb-1">{datos.plan.label}</h2>
                                    <p className="text-body-secondary">{datos.plan.descripcion}</p>
                                    <p className="mb-1"><strong>{precioMensual(datos.plan.precio_uf)}</strong></p>
                                    {datos.plan.desde && <p className="small text-body-secondary">Contratado desde el {new Date(datos.plan.desde).toLocaleDateString('es-CL')}.</p>}
                                    <h3 className="h6 mt-3">Incluye</h3>
                                    <ul className="small mb-0 ps-3">
                                        {datos.plan.funcionalidades.map((funcionalidad) => <li key={funcionalidad} className="mb-1">{funcionalidad}</li>)}
                                    </ul>
                                </Card.Body>
                            </Card>
                        </Col>
                        <Col lg={6}>
                            <Card as="section" className="h-100" aria-labelledby="plan-cobro">
                                <Card.Body>
                                    <h2 id="plan-cobro" className="h5">Cobro mensual</h2>
                                    <p className="small text-body-secondary">
                                        Incluye {datos.sucursales_incluidas === 1 ? 'tu sucursal principal' : datos.sucursales_incluidas+' sucursales'} sin cobro adicional, y cada sucursal incluye una caja.
                                    </p>
                                    <ul className="list-unstyled mb-0">
                                        {datos.lineas.map((linea, indice) => (
                                            <li key={indice} className="d-flex justify-content-between gap-3 py-2 border-bottom">
                                                <span className="min-w-0">{linea.concepto}{linea.detalle && <span className="d-block small text-body-secondary">{linea.detalle}</span>}</span>
                                                <span className="text-nowrap text-end">{linea.precio_uf === null ? <span className="text-body-secondary">Por confirmar</span> : formatoUf(linea.precio_uf)+' UF'}</span>
                                            </li>
                                        ))}
                                        <li className="d-flex justify-content-between gap-3 pt-2 fw-semibold">
                                            <span>Total mensual</span>
                                            <span className="text-nowrap text-end">{datos.total_mensual_uf === null ? 'Por confirmar' : formatoUf(datos.total_mensual_uf)+' UF + IVA'}</span>
                                        </li>
                                    </ul>
                                </Card.Body>
                            </Card>
                        </Col>
                    </Row>

                    <Card as="section" aria-labelledby="plan-cambiar">
                        <Card.Body>
                            <h2 id="plan-cambiar" className="h5">Cambiar de plan</h2>
                            <p className="text-body-secondary">El cambio rige desde hoy. Los módulos que el nuevo plan no incluye dejan de estar disponibles, sin borrar sus datos.</p>
                            <Row className="g-3">
                                {datos.opciones.map((opcion) => (
                                    <Col md={6} key={opcion.value}>
                                        <div className={`border rounded p-3 h-100 d-flex flex-column ${opcion.actual ? 'border-primary' : ''}`}>
                                            <div className="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                <h3 className="h6 mb-0">{opcion.label}</h3>
                                                {opcion.actual && <Badge bg="primary-subtle" text="primary" className="fw-normal">Plan actual</Badge>}
                                            </div>
                                            <p className="small text-body-secondary mb-2">{opcion.descripcion}</p>
                                            <p className="small mb-3">{precioMensual(opcion.precio_uf)}</p>
                                            {!opcion.actual && <Button variant="outline-primary" className="mt-auto align-self-start" onClick={() => setOpcionElegida(opcion)}>Cambiar a {opcion.label}</Button>}
                                        </div>
                                    </Col>
                                ))}
                            </Row>
                        </Card.Body>
                    </Card>
                    {opcionElegida && <CambiarPlan opcion={opcionElegida} actual={datos.plan.label} onCancel={() => setOpcionElegida(null)} onCambiado={alCambiar} />}
                </>
            )}
        </>
    );
}
