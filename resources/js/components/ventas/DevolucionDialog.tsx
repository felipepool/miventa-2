import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Form, Spinner } from 'react-bootstrap';
import { FormDialog, TextField } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { formatearCantidad, pasoDeCantidad } from '@/lib/inventario';
import { nuevaClaveDeVenta, redondearEfectivo } from '@/lib/venta';
import type { MedioPago, Venta } from '@/types/venta';

interface DevolucionDialogProps {
    venta: Venta;
    medios: { value: MedioPago; label: string }[];
    onHide: () => void;
    onDevuelta: (venta: Venta) => void;
}

/** Cantidad que aún se puede devolver de una línea, con 3 decimales. */
const disponibleDe = (cantidad: string, devuelta: string | null) => Math.round((Number(cantidad)-Number(devuelta ?? 0))*1000)/1000;

/** Medio con el que se pagó la mayor parte de la venta: es el que se propone para devolver. */
function medioPrincipal(venta: Venta): MedioPago {
    return [...(venta.pagos ?? [])].sort((a, b) => b.monto-a.monto)[0]?.medio ?? 'efectivo';
}

/**
 * Devolución de productos de una venta, total o por líneas. El monto que se muestra es una
 * estimación: el servidor lo calcula de nuevo con lo cobrado por cada línea.
 */
export default function DevolucionDialog({ venta, medios, onHide, onDevuelta }: DevolucionDialogProps) {
    const formId = useId();
    const lineas = (venta.items ?? []).map((item) => ({ item, disponible: disponibleDe(item.cantidad, item.devuelta) })).filter(({ disponible }) => disponible > 0);
    const [cantidades, setCantidades] = useState<Record<number, string>>({});
    const [medio, setMedio] = useState<MedioPago>(() => medioPrincipal(venta));
    const [motivo, setMotivo] = useState('');
    const [reintegrar, setReintegrar] = useState(true);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const clave = useRef(nuevaClaveDeVenta());
    const formulario = useRef<HTMLFormElement>(null);
    const elegidas = lineas.filter(({ item }) => Number(cantidades[item.linea] || 0) > 0);
    const estimado = elegidas.reduce((suma, { item }) => suma+Math.round(item.total*Number(cantidades[item.linea])/Number(item.cantidad)), 0);
    const saldo = Math.max(0, venta.total-(venta.total_devuelto ?? 0));
    const aDevolver = medio === 'efectivo'
        ? Math.min(redondearEfectivo(estimado), Math.floor(saldo/10)*10)
        : Math.min(estimado, saldo);

    useEffect(() => {
        if (Object.keys(errores).length) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const devolverTodo = () => setCantidades(Object.fromEntries(lineas.map(({ item, disponible }) => [item.linea, String(disponible)])));

    const devolver = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            const respuesta = await api.post<{ data: Venta }>('/ventas/'+venta.id+'/devoluciones', {
                items: elegidas.map(({ item }) => ({ linea: item.linea, cantidad: cantidades[item.linea] })),
                medio, motivo: motivo || null, reintegrar_stock: reintegrar, clave_idempotencia: clave.current,
            });
            onDevuelta(respuesta.data.data);
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(validacion.venta ?? validacion.turno ?? (Object.keys(validacion).length ? '' : mensajeDeError(exception)));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    /** Error de la cantidad de una línea: el servidor lo indexa según el orden de las líneas enviadas. */
    const errorDeLinea = (linea: number) => {
        const posicion = elegidas.findIndex(({ item }) => item.linea === linea);

        return posicion < 0 ? undefined : errores['items.'+posicion+'.cantidad'] ?? errores['items.'+posicion+'.linea'];
    };

    return <FormDialog title={'Devolver productos · Venta N° '+venta.folio} size="lg" onHide={onHide} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={onHide}>Volver</Button>
        <Button type="submit" form={formId} disabled={procesando || elegidas.length === 0}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Registrando…' : 'Devolver '+formatearMonto(aDevolver)}
        </Button>
    </>}>
        <form ref={formulario} id={formId} className="record-form devolucion" noValidate aria-busy={procesando} onSubmit={(event) => void devolver(event)}>
            <fieldset disabled={procesando} className="record-form__section">
                <legend className="h6 d-flex align-items-center justify-content-between gap-2">
                    <span>Productos a devolver</span>
                    <Button variant="link" size="sm" className="p-0" onClick={devolverTodo}>Devolver todo</Button>
                </legend>
                <ul className="devolucion__lineas list-unstyled mb-0">
                    {lineas.map(({ item, disponible }) => <li key={item.linea} className="devolucion__linea">
                        <div className="min-w-0">
                            <strong className="d-block text-break">{item.nombre}</strong>
                            <small className="text-body-secondary">Vendido {formatearCantidad(item.cantidad, item.unidad_venta)} · {formatearMonto(item.total)}
                                {Number(item.devuelta) > 0 ? ' · ya devuelto '+formatearCantidad(item.devuelta ?? '0', item.unidad_venta) : ''}</small>
                        </div>
                        <TextField label={'Cantidad a devolver de '+item.nombre} className="devolucion__cantidad mb-0" type="number" min="0" max={disponible} step={pasoDeCantidad(item.unidad_venta)}
                            inputMode={item.unidad_venta === 'unidad' ? 'numeric' : 'decimal'} placeholder="0" value={cantidades[item.linea] ?? ''} error={errorDeLinea(item.linea)}
                            hint={'Hasta '+formatearCantidad(String(disponible), item.unidad_venta)} onChange={(event) => setCantidades((actuales) => ({ ...actuales, [item.linea]: event.target.value }))} />
                    </li>)}
                </ul>
                {errores.items && <p className="text-danger small mt-2 mb-0" role="alert">{errores.items}</p>}
            </fieldset>
            <fieldset disabled={procesando} className="record-form__section">
                <legend className="h6">Reembolso</legend>
                <Form.Group controlId={formId+'-medio'} className="mb-3">
                    <Form.Label>Devolver el dinero con</Form.Label>
                    <Form.Select value={medio} isInvalid={Boolean(errores.medio)} aria-invalid={errores.medio ? true : undefined} onChange={(event) => setMedio(event.target.value as MedioPago)}>
                        {medios.map((opcion) => <option key={opcion.value} value={opcion.value}>{opcion.label}</option>)}
                    </Form.Select>
                    {errores.medio ? <Form.Control.Feedback type="invalid">{errores.medio}</Form.Control.Feedback>
                        : <Form.Text muted>{medio === 'efectivo' ? 'Sale de tu caja, redondeado a $10.' : 'Se registra en tu turno; no cambia el efectivo de la caja.'}</Form.Text>}
                </Form.Group>
                <TextField label="Motivo" value={motivo} maxLength={150} required error={errores.motivo} hint="Por ejemplo: producto con falla." onChange={(event) => setMotivo(event.target.value)} />
                <Form.Check id={formId+'-reintegrar'} type="switch" className="mb-3" checked={reintegrar} onChange={(event) => setReintegrar(event.target.checked)}
                    label="Los productos vuelven al stock" />
                <div className="devolucion__total" aria-live="polite">
                    <span>Monto a devolver</span><strong>{formatearMonto(aDevolver)}</strong>
                </div>
                <Form.Text muted>Monto estimado: se calcula según lo que se cobró por cada producto, con sus descuentos.</Form.Text>
                {errorGeneral && <p className="text-danger mt-3 mb-0" role="alert">{errorGeneral}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}
