import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Spinner } from 'react-bootstrap';
import { FormDialog, Icon, TextField } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { nuevaClaveDeVenta, resumirCobro } from '@/lib/venta';
import type { MedioPago, Venta } from '@/types/venta';

type MedioSinEfectivo = Exclude<MedioPago, 'efectivo'>;

const ICONOS: Record<MedioPago, string> = { efectivo: 'cash-coin', debito: 'credit-card', credito: 'credit-card-2-front', transferencia: 'bank' };

/** Billetes chilenos para sugerir con cuánto paga el cliente. */
const BILLETES = [1000, 5000, 10000, 20000];

/** Hasta tres montos redondos mayores al efectivo a cobrar, de menor a mayor. */
function montosSugeridos(monto: number): number[] {
    const redondos = BILLETES.map((billete) => Math.ceil(monto/billete)*billete).filter((valor) => valor > monto);

    return [...new Set(redondos)].sort((a, b) => a-b).slice(0, 3);
}

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
    const [seleccion, setSeleccion] = useState<MedioPago | 'dividido'>('efectivo');
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
        setSeleccion(medio);
        setRecibido('');
        setMontos({ debito: '', credito: '', transferencia: '', ...(medio === 'efectivo' ? {} : { [medio]: String(base) }) });
    };

    const dividir = () => {
        setSeleccion('dividido');
        setRecibido('');
        setMontos({ debito: '', credito: '', transferencia: '' });
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
        <Button type="submit" form="cobro-form" className="cobro__confirmar" disabled={!puedeCobrar}>
            {procesando ? <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" /> : <Icon name="check-lg" className="me-2" />}
            {procesando ? 'Registrando…' : 'Cobrar '+formatearMonto(total)}
        </Button>
    </>}>
        <form id="cobro-form" className="cobro" noValidate aria-busy={procesando} onSubmit={(event) => void cobrar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Medios de pago</legend>
                <div className="cobro__total">
                    <span className="cobro__etiqueta">Total a cobrar</span>
                    <strong className="cobro__monto" aria-live="polite">{formatearMonto(total)}</strong>
                    {usaEfectivo && cobro.redondeo !== 0 && <small className="text-body-secondary">Venta {formatearMonto(base)} · redondeo a $10 {formatearMonto(cobro.redondeo)}</small>}
                </div>

                <div className="cobro__medios" role="group" aria-label="Forma de pago">
                    {medios.map((medio) => <button key={medio.value} type="button" className={'cobro__medio'+(seleccion === medio.value ? ' is-activo' : '')} aria-pressed={seleccion === medio.value}
                        onClick={() => pagarTodoCon(medio.value)}><Icon name={ICONOS[medio.value]} />{medio.label}</button>)}
                    {sinEfectivo.length > 0 && <button type="button" className={'cobro__medio'+(seleccion === 'dividido' ? ' is-activo' : '')} aria-pressed={seleccion === 'dividido'} onClick={dividir}>
                        <Icon name="pie-chart" />Dividir</button>}
                </div>

                {seleccion === 'dividido' && <div className="cobro__seccion">
                    <p className="small text-body-secondary mb-2">Indica cuánto se paga con cada medio. Lo que falte se cobra en efectivo.</p>
                    <div className="row g-2">
                        {sinEfectivo.map((medio) => <div key={medio.value} className="col-12 col-sm-4">
                            <TextField label={medio.label} type="number" min="0" step="1" inputMode="numeric" className="mb-0" value={montos[medio.value as MedioSinEfectivo]} placeholder="0"
                                onChange={(event) => setMontos((actuales) => ({ ...actuales, [medio.value]: event.target.value }))} />
                        </div>)}
                    </div>
                    {sobra && <p className="text-danger small mt-2 mb-0" role="alert">Los pagos superan el total de la venta.</p>}
                </div>}

                {usaEfectivo ? <div className="cobro__seccion">
                    {seleccion === 'dividido' && <div className="d-flex justify-content-between mb-2"><span>Resto en efectivo</span><strong>{formatearMonto(cobro.enEfectivo)}</strong></div>}
                    <TextField label="Efectivo recibido" type="number" min="0" step="1" inputMode="numeric" size="lg" className="mb-2" value={recibido} placeholder={String(cobro.enEfectivo)} autoFocus
                        error={faltaEfectivo ? 'El efectivo recibido no alcanza.' : undefined} hint="Déjalo vacío si pagó justo." onChange={(event) => setRecibido(event.target.value)} />
                    <div className="cobro__rapidos" role="group" aria-label="Montos rápidos">
                        <Button variant={recibido === '' ? 'primary' : 'outline-secondary'} size="sm" aria-pressed={recibido === ''} onClick={() => setRecibido('')}>Justo</Button>
                        {montosSugeridos(cobro.enEfectivo).map((monto) => <Button key={monto} variant={Number(recibido) === monto ? 'primary' : 'outline-secondary'} size="sm" aria-pressed={Number(recibido) === monto}
                            onClick={() => setRecibido(String(monto))}>{formatearMonto(monto)}</Button>)}
                    </div>
                    <div className={'cobro__vuelto'+(vuelto > 0 ? ' is-positivo' : '')}>
                        <span>Vuelto</span><strong aria-live="polite">{formatearMonto(vuelto)}</strong>
                    </div>
                </div> : !sobra && <div className="cobro__seccion cobro__aviso">
                    <Icon name={seleccion === 'dividido' ? 'check-circle' : ICONOS[seleccion]} />
                    <span>{seleccion === 'dividido' ? 'El total queda cubierto con los medios indicados.' : 'Cobra '+formatearMonto(total)+' con '+(medios.find((medio) => medio.value === seleccion)?.label.toLowerCase() ?? '')+'.'}</span>
                </div>}

                {errorGeneral && <p className="text-danger mt-3 mb-0" role="alert">{errorGeneral}</p>}
                {errores.turno && <p className="text-danger small mb-0" role="alert">{errores.turno}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}
