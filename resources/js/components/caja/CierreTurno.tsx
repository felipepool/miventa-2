import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Form, Spinner } from 'react-bootstrap';
import { FormDialog, TextField } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import type { TurnoCaja } from '@/types/turno';

interface CierreTurnoProps {
    /** Ruta de la API que cierra el turno (el propio o el de otra persona). */
    ruta: string;
    titulo: string;
    denominaciones: number[];
    onCerrado: (turno: TurnoCaja) => void;
    onHide: () => void;
}

/**
 * Diálogo de cierre: se cuentan los billetes y monedas (o se escribe el total
 * contado) y el servidor calcula y congela el esperado y la diferencia.
 */
export default function CierreTurno({ ruta, titulo, denominaciones, onCerrado, onHide }: CierreTurnoProps) {
    const formId = useId();
    const formulario = useRef<HTMLFormElement>(null);
    const [porDenominacion, setPorDenominacion] = useState(true);
    const [conteo, setConteo] = useState<Record<number, string>>({});
    const [total, setTotal] = useState('');
    const [nota, setNota] = useState('');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const totalContado = denominaciones.reduce((suma, valor) => suma+valor*Number(conteo[valor] || 0), 0);

    useEffect(() => {
        if (Object.keys(errores).length) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const cerrar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        const contado = porDenominacion
            ? { conteo: Object.fromEntries(denominaciones.map((valor) => [valor, Number(conteo[valor] || 0)])) }
            : { monto_contado: total.trim() === '' ? null : Number(total) };
        try {
            const respuesta = await api.post<{ data: TurnoCaja }>(ruta, { ...contado, nota: nota || null });
            onCerrado(respuesta.data.data);
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(Object.keys(validacion).length ? '' : mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return <FormDialog title={titulo} size="md" onHide={onHide} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={onHide}>Cancelar</Button>
        <Button type="submit" form={formId} variant="danger" disabled={procesando}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Cerrando…' : 'Cerrar caja'}
        </Button>
    </>}>
        <form ref={formulario} id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void cerrar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Conteo del efectivo</legend>
                <Form.Check type="switch" id="cierre-por-denominacion" className="mb-3" label="Contar billetes y monedas" checked={porDenominacion}
                    onChange={(event) => setPorDenominacion(event.target.checked)} />
                {porDenominacion ? <>
                    <div className="row g-2 mb-3">
                        {denominaciones.map((valor) => <div key={valor} className="col-6">
                            <TextField label={formatearMonto(valor)} type="number" min="0" step="1" inputMode="numeric" className="mb-0" value={conteo[valor] ?? ''} placeholder="0"
                                error={errores['conteo.'+valor]} onChange={(event) => setConteo((actual) => ({ ...actual, [valor]: event.target.value }))} />
                        </div>)}
                    </div>
                    {errores.conteo && <p className="text-danger small" role="alert">{errores.conteo}</p>}
                    <p className="fw-semibold" role="status">Total contado: {formatearMonto(totalContado)}</p>
                </> : <TextField label="Total contado (CLP)" type="number" min="0" step="1" inputMode="numeric" value={total} required error={errores.monto_contado} onChange={(event) => setTotal(event.target.value)} />}
                {porDenominacion && errores.monto_contado && <p className="text-danger small" role="alert">{errores.monto_contado}</p>}
                <TextField label="Nota (opcional)" value={nota} maxLength={255} error={errores.nota} onChange={(event) => setNota(event.target.value)} />
                <p className="small text-body-secondary mb-0">El cierre es definitivo: no se puede corregir después.</p>
                {errorGeneral && <p className="text-danger mt-3" role="alert">{errorGeneral}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}

