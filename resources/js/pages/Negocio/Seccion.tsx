import { useCallback, useEffect, useId, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Button, Card, Spinner } from 'react-bootstrap';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import type { Negocio } from '@/types/negocio';

/**
 * Guardado independiente de una sección de Mi negocio: evita envíos duplicados
 * y conserva lo escrito si el servidor rechaza los cambios.
 */
export function useGuardadoSeccion(ruta: string, onGuardado: (negocio: Negocio) => void) {
    const [procesando, setProcesando] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const envioEnCurso = useRef(false);

    const guardar = useCallback(async (payload: Record<string, unknown>) => {
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            const respuesta = await api.put<{ data: Negocio }>(ruta, payload);
            onGuardado(respuesta.data.data);
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(Object.keys(validacion).length ? '' : mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    }, [ruta, onGuardado]);

    return { guardar, procesando, errores, errorGeneral };
}

interface SeccionProps {
    title: string;
    description: string;
    submitLabel: string;
    processing: boolean;
    errores: Record<string, string>;
    errorGeneral: string;
    onSubmit: () => void;
    children: ReactNode;
}

/** Sección de configuración con su propio formulario, error y botón de guardado. */
export default function Seccion({ title, description, submitLabel, processing, errores, errorGeneral, onSubmit, children }: SeccionProps) {
    const tituloId = useId();
    const formulario = useRef<HTMLFormElement>(null);

    useEffect(() => {
        if (Object.keys(errores).length) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const enviar = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        onSubmit();
    };

    return (
        <Card as="section" className="mb-4" aria-labelledby={tituloId}>
            <Card.Body>
                <h2 id={tituloId} className="h5">{title}</h2>
                <p className="text-body-secondary">{description}</p>
                <form ref={formulario} noValidate aria-busy={processing} onSubmit={enviar}>
                    <fieldset disabled={processing}>
                        <legend className="visually-hidden">{title}</legend>
                        {children}
                    </fieldset>
                    {errorGeneral && <p className="text-danger" role="alert">{errorGeneral}</p>}
                    <div className="d-flex justify-content-end">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
                            {processing ? 'Guardando…' : submitLabel}
                        </Button>
                    </div>
                </form>
            </Card.Body>
        </Card>
    );
}
