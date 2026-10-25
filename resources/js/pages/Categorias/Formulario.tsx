import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Spinner } from 'react-bootstrap';
import { useLocation, useNavigate, useOutletContext } from 'react-router-dom';
import { FormDialog, TextField } from '@/components/ui';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import type { ElementoCatalogo } from '@/types/catalogo';
import type { CategoriasContexto } from './Index';

export default function CategoriaFormulario({ categoria }: { categoria?: ElementoCatalogo }) {
    const { onGuardado } = useOutletContext<CategoriasContexto>();
    const location = useLocation();
    const navigate = useNavigate();
    const formId = useId();
    const formulario = useRef<HTMLFormElement>(null);
    const [nombre, setNombre] = useState(categoria?.nombre ?? '');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const cerrar = () => navigate('/categorias'+location.search, { replace: true });

    useEffect(() => {
        if (Object.keys(errores).length) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const guardar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            const respuesta = categoria ? await api.put<{ data: ElementoCatalogo }>('/categorias/'+categoria.id, { nombre }) : await api.post<{ data: ElementoCatalogo }>('/categorias', { nombre });
            onGuardado(respuesta.data.data, categoria ? 'actualizada' : 'creada');
            cerrar();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(Object.keys(validacion).length ? '' : mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return <FormDialog title={categoria ? 'Editar categoría' : 'Nueva categoría'} size="md" onHide={cerrar} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={cerrar}>Cancelar</Button>
        <Button type="submit" form={formId} disabled={procesando}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Guardando…' : categoria ? 'Guardar cambios' : 'Crear categoría'}
        </Button>
    </>}>
        <form ref={formulario} id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void guardar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Datos de la categoría</legend>
                <TextField label="Nombre" autoComplete="off" value={nombre} maxLength={100} required error={errores.nombre} onChange={(event) => setNombre(event.target.value)} />
                {errorGeneral && <p className="text-danger" role="alert">{errorGeneral}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}
