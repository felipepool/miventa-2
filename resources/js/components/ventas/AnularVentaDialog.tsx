import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Alert, Button, Spinner } from 'react-bootstrap';
import { FormDialog, TextField } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import type { Venta } from '@/types/venta';

interface AnularVentaDialogProps {
    venta: Venta;
    onHide: () => void;
    onAnulada: (venta: Venta) => void;
}

/** Anula una venta de un turno abierto: deja de sumar a la caja y, con Inventario, devuelve el stock. */
export default function AnularVentaDialog({ venta, onHide, onAnulada }: AnularVentaDialogProps) {
    const formId = useId();
    const [motivo, setMotivo] = useState('');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const formulario = useRef<HTMLFormElement>(null);

    useEffect(() => {
        if (Object.keys(errores).length) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const anular = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            const respuesta = await api.post<{ data: Venta }>('/ventas/'+venta.id+'/anular', { motivo: motivo || null });
            onAnulada(respuesta.data.data);
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(validacion.venta ?? (Object.keys(validacion).length ? '' : mensajeDeError(exception)));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return <FormDialog title={'Anular venta N° '+venta.folio} size="md" onHide={onHide} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={onHide}>Volver</Button>
        <Button type="submit" variant="danger" form={formId} disabled={procesando}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Anulando…' : 'Anular venta'}
        </Button>
    </>}>
        <form ref={formulario} id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void anular(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Anulación de la venta</legend>
                <Alert variant="warning" className="small">
                    La venta de {formatearMonto(venta.total)} dejará de contar en la caja de su turno. Si el negocio controla stock, los productos vuelven al inventario. No se puede deshacer.
                </Alert>
                <TextField label="Motivo" value={motivo} maxLength={150} required autoFocus error={errores.motivo}
                    hint="Por ejemplo: cobro duplicado o el cliente se arrepintió antes de irse." onChange={(event) => setMotivo(event.target.value)} />
                {errorGeneral && <p className="text-danger mb-0" role="alert">{errorGeneral}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}
