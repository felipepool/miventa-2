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

function CambiarPlan({ opcion, actual, iniciales, onCancel, onCambiado }: { opcion: OpcionPlan; actual: string; iniciales: string[]; onCancel: () => void; onCambiado: () => void }) {
    const aceptacionId = useId();
    const [adicionales, setAdicionales] = useState(iniciales);
    const [excluir, setExcluir] = useState<string[]>([]);
    const parametros = new URLSearchParams({ plan: opcion.value });
    adicionales.forEach((valor) => parametros.append('adicionales[]', valor));
    excluir.forEach((valor) => parametros.append('excluir[]', valor));
    const cotizacion = useConsultaApi<{ data: CotizacionPlan }>('/plan/cotizacion?'+parametros.toString());
    const recibido = cotizacion.datos?.data;
    const [ultimaCotizacion, setUltimaCotizacion] = useState<CotizacionPlan>();
    useEffect(() => { if (recibido) { setUltimaCotizacion(recibido); } }, [recibido]);
    const cambio = recibido ?? ultimaCotizacion;
    const [catalogoAdicionales, setCatalogoAdicionales] = useState<CotizacionPlan['adicionales']>([]);
    const [recursos, setRecursos] = useState<CotizacionPlan['recursos']>([]);
    useEffect(() => { if (cambio) { setRecursos(cambio.recursos); } }, [cambio]);
    useEffect(() => { if (cambio) { setCatalogoAdicionales(cambio.adicionales); } }, [cambio]);
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
            await api.put('/plan', { plan: opcion.value, adicionales, excluir, acepto_cobro: aceptado, cotizacion: cambio.cotizacion });
            onCambiado();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrorAceptacion(validacion.acepto_cobro ?? '');
            setError(validacion.cotizacion ?? validacion.tarifa ?? validacion.plan ?? validacion.excluir ?? Object.values(validacion)[0] ?? mensajeDeError(exception));
            if (validacion.cotizacion || validacion.tarifa) { setAceptado(false); cotizacion.recargar(); }
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return (
        <ConfirmModal title={'Contratar '+opcion.label} confirmLabel="Confirmar contratación" processing={procesando} error={error}
            confirmDisabled={!cambio || cotizacion.cargando || Boolean(cotizacion.error) || cambio.total_mensual_uf === null || !aceptado} onConfirm={() => void confirmar()} onCancel={onCancel}>
            <p>Plan elegido: <strong>{opcion.label}</strong>. {actual !== opcion.label && <>Reemplaza tu plan {actual}. </>}{opcion.descripcion}</p>
            <fieldset disabled={procesando} className="mb-3">
                <legend className="fs-6">Adicionales que quieres contratar</legend>
                {catalogoAdicionales.map((item) => <Form.Check key={item.value} id={aceptacionId+'-'+item.value}
                    label={`${item.label}: ${precioMensual(item.precio_uf)}`} checked={adicionales.includes(item.value)}
                    onChange={(event) => { setAceptado(false); setAdicionales((valores) => event.target.checked ? [...valores, item.value] : valores.filter((valor) => valor !== item.value)); }} />)}
            </fieldset>
            {recursos.length > 0 && <fieldset disabled={procesando} className="mb-3">
                <legend className="fs-6">Sucursales y cajas extras que conservarás</legend>
                {recursos.map((item) => <Form.Check key={item.value} id={aceptacionId+'-'+item.value}
                    label={`${item.concepto}: ${item.detalle} · ${precioMensual(item.precio_uf)}`}
                    disabled={item.value.startsWith('caja:') && excluir.includes('sucursal:'+item.sucursal)}
                    checked={!excluir.includes(item.value) && !excluir.includes('sucursal:'+item.sucursal)}
                    onChange={(event) => { setAceptado(false); setExcluir((valores) => event.target.checked ? valores.filter((valor) => valor !== item.value) : [...valores, item.value]); }} />)}
                <p className="small mt-2 mb-0">Al confirmar, las extras desmarcadas se desactivarán inmediatamente, conservando sus datos. Las que mantengas activas tendrán el cobro mensual indicado.</p>
            </fieldset>}
            <p role="status" className="small text-body-secondary">{cotizacion.cargando ? 'Actualizando el desglose…' : 'Revisa el desglose antes de confirmar.'}</p>
            {cotizacion.error ? <div role="alert"><p>No se pudieron consultar las condiciones.</p><Button variant="outline-secondary" onClick={cotizacion.recargar}>Reintentar</Button></div>
                : cambio && <>
                    <p className="small">Facturación electrónica: boletas, facturas y notas de débito y crédito de prueba con motor-dte, sin valor tributario. No se habilita emisión en producción.</p>
                    <ul className="list-unstyled small">{cambio.lineas.map((linea, indice) => <li key={indice} className="d-flex justify-content-between gap-3 py-1">
                        <span>{linea.concepto}{linea.detalle && <> · {linea.detalle}</>}</span><span className="text-nowrap">{linea.precio_uf === null ? 'Por confirmar' : formatoUf(linea.precio_uf)+' UF'}</span>
                    </li>)}</ul>
                    <p className="fw-semibold">Total: {precioMensual(cambio.total_mensual_uf)}</p>
                    <p className="small">El total incluye las sucursales y cajas extras que elegiste conservar.</p>
                    {cambio.total_mensual_uf === null ? <Alert variant="warning" className="small">Hay un precio sin configurar en el desglose. Contacta a soporte antes de contratar.</Alert>
                        : <Alert variant="warning" className="small">
                            <p className="mb-1">Inicio del cobro: <strong>{new Date(cambio.cobro_desde).toLocaleDateString('es-CL')}</strong>. Contratar durante la prueba conserva los 14 días gratuitos.</p>
                            <p className="mb-0">Tus sucursales y cajas adicionales conservan su precio contratado.</p>
                        </Alert>}
                    {cambio.modulos_agregados.length > 0 && <p className="small mb-2"><Icon name="plus-circle" className="me-1 text-success" />Se activan: {cambio.modulos_agregados.map((modulo) => modulo.label).join(', ')}.</p>}
                    {cambio.modulos_retirados.length > 0 && <p className="small mb-2"><Icon name="dash-circle" className="me-1 text-danger" />Dejan de estar disponibles: {cambio.modulos_retirados.map((modulo) => modulo.label).join(', ')}. Sus datos se conservan.</p>}
                    {cambio.sin_retorno && actual !== opcion.label && <p className="small mb-2"><Icon name="exclamation-triangle" className="me-1 text-warning" />El plan {actual} ya no se ofrece: no podrás volver a él.</p>}
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
        notificar('Contratación guardada: '+opcionElegida?.label+'.');
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
                                    {datos.plan.desde && <p className="small text-body-secondary">Inicio del cobro: {new Date(datos.plan.desde).toLocaleDateString('es-CL')}.</p>}
                                    {!datos.estado.contratado && <p className="small text-body-secondary">Aún no tienes una contratación registrada.</p>}
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
                            <h2 id="plan-cambiar" className="h5">Plan y adicionales</h2>
                            <p className="text-body-secondary">Elige qué conservar. Durante la prueba no se cobra; al terminar se aplican las condiciones aceptadas. Los datos de funciones no contratadas se conservan.</p>
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
                                            <Button variant="outline-primary" className="mt-auto align-self-start" onClick={() => setOpcionElegida(opcion)}>{opcion.actual && datos.estado.contratado ? 'Administrar adicionales' : 'Contratar '+opcion.label}</Button>
                                        </div>
                                    </Col>
                                ))}
                            </Row>
                        </Card.Body>
                    </Card>
                    {opcionElegida && <CambiarPlan opcion={opcionElegida} actual={datos.plan.label} iniciales={datos.adicionales_contratados} onCancel={() => setOpcionElegida(null)} onCambiado={alCambiar} />}
                </>
            )}
        </>
    );
}
