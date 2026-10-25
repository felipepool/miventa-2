import { useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Spinner } from 'react-bootstrap';
import { FormDialog, TextField } from '@/components/ui';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { formatearCantidad, pasoDeCantidad } from '@/lib/inventario';
import type { ItemInventario } from '@/types/inventario';
import DialogoProducto from './DialogoProducto';

export default function MinimoFormulario() {
    return <DialogoProducto title="Definir stock mínimo">
        {(item, cerrar, contexto) => <Contenido key={item.id} item={item} cerrar={cerrar} onGuardado={contexto.onGuardado} />}
    </DialogoProducto>;
}

interface ContenidoProps {
    item: ItemInventario;
    cerrar: () => void;
    onGuardado: (mensaje: string) => void;
}

function Contenido({ item, cerrar, onGuardado }: ContenidoProps) {
    const formId = useId();
    const [minimo, setMinimo] = useState(Number(item.stock_minimo) > 0 ? String(Number(item.stock_minimo)) : '');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);

    const guardar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            await api.put('/inventario/'+item.id+'/minimo', { stock_minimo: minimo.trim() === '' ? '0' : minimo.trim() });
            onGuardado('Stock mínimo de '+item.nombre+' actualizado correctamente.');
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

    return <FormDialog title="Definir stock mínimo" size="md" onHide={cerrar} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={cerrar}>Cancelar</Button>
        <Button type="submit" form={formId} disabled={procesando}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Guardando…' : 'Guardar'}
        </Button>
    </>}>
        <form id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void guardar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Stock mínimo</legend>
                <p className="mb-3"><strong>{item.nombre}</strong><small className="d-block text-body-secondary">Stock actual: {formatearCantidad(item.cantidad, item.unidad_venta)}</small></p>
                <TextField label="Stock mínimo" type="number" min="0" step={pasoDeCantidad(item.unidad_venta)} inputMode="decimal" value={minimo}
                    hint="Cuando el stock llegue a este valor o menos, el producto aparece como «Stock bajo». Déjalo en 0 para no usar alerta." error={errores.stock_minimo}
                    onChange={(event) => setMinimo(event.target.value)} />
                {errorGeneral && <p className="text-danger" role="alert">{errorGeneral}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}
