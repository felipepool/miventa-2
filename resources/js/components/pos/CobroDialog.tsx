import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, ButtonGroup, Spinner } from 'react-bootstrap';
import { FormDialog, TextField } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { nuevaClaveDeVenta, resumirCobro } from '@/lib/venta';
import type { MedioPago, Venta } from '@/types/venta';

type MedioSinEfectivo = Exclude<MedioPago, 'efectivo'>;

interface CobroDialogProps {
    /** Lo que se debe cobrar después de los descuentos, sin redondeo. */
    base: number;
    medios: { value: MedioPago; label: string }[];
    /** Arma el cuerpo de la venta con los pagos elegidos. */
    construirVenta: (pagos: Record<string, unknown>[], clave: string) => Record<string, unknown>;
    onVendida: (venta: Venta) => void;
    onHide: () => void;
}

/**
 * Cobro de la venta. Los medios distintos de efectivo llevan su monto y el efectivo cubre el
 * resto, redondeado a $10, con el vuelto calculado. El servidor valida y calcula de nuevo.
 */
export default function CobroDialog({ base, medios, construirVenta, onVendida, onHide }: CobroDialogProps) {
    const [montos, setMontos] = useState<Record<MedioSinEfectivo, string>>({ debito: '', credito: '', transferencia: '' });
    const [recibido, setRecibido] = useState('');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const clave = useRef(nuevaClaveDeVenta());
    const sinEfectivo = medios.filter((medio) => medio.value !== 'efectivo');
    const numeros = sinEfectivo.map((medio) => Number(montos[medio.value as MedioSinEfectivo] || 0));
    const cobro = resumirCobro(base, numeros, true);
    const usaEfectivo = cobro.porCobrar > 0;
    const sobra = cobro.sinEfectivo > base;
    const recibidoNumero = recibido.trim() === '' ? cobro.enEfectivo : Number(recibido);
    const faltaEfectivo = usaEfectivo && recibidoNumero < cobro.enEfectivo;
    const vuelto = usaEfectivo && !faltaEfectivo ? recibidoNumero-cobro.enEfectivo : 0;
    const total = usaEfectivo ? cobro.total : cobro.sinEfectivo;
    const puedeCobrar = !sobra && !faltaEfectivo && !procesando;

    const pagarTodoCon = (medio: MedioPago) => {
        setRecibido('');
        setMontos({ debito: '', credito: '', transferencia: '', ...(medio === 'efectivo' ? {} : { [medio]: String(base) }) });
    };

    const cobrar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!puedeCobrar || envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        const pagos: Record<string, unknown>[] = sinEfectivo.flatMap((medio, indice) => ((numeros[indice] ?? 0) > 0 ? [{ medio: medio.value, monto: numeros[indice] }] : []));
        if (usaEfectivo) { pagos.push({ medio: 'efectivo', ...(recibido.trim() === '' ? {} : { recibido: Number(recibido) }) }); }
        try {
            const respuesta = await api.post<{ data: Venta }>('/ventas', construirVenta(pagos, clave.current));
            onVendida(respuesta.data.data);
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(Object.values(validacion).join(' ') || mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return <FormDialog title="Cobrar" size="md" onHide={onHide} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={onHide}>Volver</Button>
        <Button type="submit" form="cobro-form" disabled={!puedeCobrar}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Registrando…' : 'Confirmar · '+formatearMonto(total)}
        </Button>
    </>}>
        <form id="cobro-form" noValidate aria-busy={procesando} onSubmit={(event) => void cobrar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Medios de pago</legend>
                <p className="fs-3 fw-semibold mb-1" aria-live="polite">{formatearMonto(base)}</p>
                <p className="text-body-secondary small">Total de la venta. Paga todo con un medio o reparte entre varios.</p>
                <ButtonGroup className="w-100 mb-3 flex-wrap" aria-label="Pagar todo con un medio">
                    {medios.map((medio) => <Button key={medio.value} variant="outline-primary" onClick={() => pagarTodoCon(medio.value)}>{medio.label}</Button>)}
                </ButtonGroup>
                <div className="row g-2">
                    {sinEfectivo.map((medio) => <div key={medio.value} className="col-12 col-sm-4">
                        <TextField label={medio.label+' (CLP)'} type="number" min="0" step="1" inputMode="numeric" className="mb-0" value={montos[medio.value as MedioSinEfectivo]} placeholder="0"
                            onChange={(event) => setMontos((actuales) => ({ ...actuales, [medio.value]: event.target.value }))} />
                    </div>)}
                </div>
                {sobra && <p className="text-danger small mt-2" role="alert">Los pagos superan el total de la venta.</p>}
                {usaEfectivo ? <div className="border rounded p-3 mt-3">
                    <div className="d-flex justify-content-between"><span>Efectivo a cobrar</span><strong>{formatearMonto(cobro.enEfectivo)}</strong></div>
                    {cobro.redondeo !== 0 && <div className="d-flex justify-content-between small text-body-secondary"><span>Redondeo a $10</span><span>{formatearMonto(cobro.redondeo)}</span></div>}
                    <TextField label="Efectivo recibido (CLP)" type="number" min="0" step="1" inputMode="numeric" className="mt-3 mb-2" value={recibido} placeholder={String(cobro.enEfectivo)}
                        error={faltaEfectivo ? 'El efectivo recibido no alcanza.' : undefined} hint="Déjalo vacío si pagó justo." onChange={(event) => setRecibido(event.target.value)} />
                    <div className="d-flex justify-content-between fs-5"><span>Vuelto</span><strong aria-live="polite">{formatearMonto(vuelto)}</strong></div>
                </div> : !sobra && <p className="text-body-secondary small mt-3 mb-0">El total queda cubierto con los medios indicados.</p>}
                {errorGeneral && <p className="text-danger mt-3" role="alert">{errorGeneral}</p>}
                {errores.turno && <p className="text-danger small" role="alert">{errores.turno}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}
