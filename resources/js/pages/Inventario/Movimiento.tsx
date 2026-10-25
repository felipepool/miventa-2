import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Form, Spinner } from 'react-bootstrap';
import { FormDialog, TextField } from '@/components/ui';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { formatearCantidad, pasoDeCantidad } from '@/lib/inventario';
import type { ItemInventario, MovimientoStock, TipoMovimiento } from '@/types/inventario';
import DialogoProducto from './DialogoProducto';

const TIPOS: { valor: TipoMovimiento; etiqueta: string; ayuda: string }[] = [
    { valor: 'entrada', etiqueta: 'Entrada', ayuda: 'Suma stock: compra o ingreso de mercadería.' },
    { valor: 'salida', etiqueta: 'Salida', ayuda: 'Resta stock: merma, consumo o pérdida.' },
    { valor: 'ajuste', etiqueta: 'Ajuste', ayuda: 'Fija el stock real después de contar.' },
];

export default function MovimientoFormulario() {
    return <DialogoProducto title="Registrar movimiento">
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
    const formulario = useRef<HTMLFormElement>(null);
    const [tipo, setTipo] = useState<TipoMovimiento>('entrada');
    const [cantidad, setCantidad] = useState('');
    const [motivo, setMotivo] = useState('');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const ayuda = TIPOS.find((opcion) => opcion.valor === tipo)?.ayuda;

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
            const respuesta = await api.post<{ data: MovimientoStock }>('/inventario/'+item.id+'/movimientos', {
                tipo, cantidad: cantidad.trim() === '' ? null : cantidad.trim(), motivo: motivo.trim() === '' ? null : motivo.trim(),
            });
            onGuardado('Movimiento registrado. Stock de '+item.nombre+': '+formatearCantidad(respuesta.data.data.stock_resultante, item.unidad_venta)+'.');
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

    return <FormDialog title="Registrar movimiento" size="md" onHide={cerrar} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={cerrar}>Cancelar</Button>
        <Button type="submit" form={formId} disabled={procesando}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Guardando…' : 'Registrar'}
        </Button>
    </>}>
        <form ref={formulario} id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void guardar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Movimiento de inventario</legend>
                <p className="mb-3"><strong>{item.nombre}</strong><small className="d-block text-body-secondary">Stock actual: {formatearCantidad(item.cantidad, item.unidad_venta)}</small></p>
                <Form.Group controlId="movimiento-tipo" className="mb-3"><Form.Label>Tipo de movimiento</Form.Label>
                    <Form.Select value={tipo} isInvalid={Boolean(errores.tipo)} onChange={(event) => setTipo(event.target.value as TipoMovimiento)}>
                        {TIPOS.map((opcion) => <option key={opcion.valor} value={opcion.valor}>{opcion.etiqueta}</option>)}
                    </Form.Select>
                    {errores.tipo ? <Form.Control.Feedback type="invalid">{errores.tipo}</Form.Control.Feedback> : <Form.Text muted>{ayuda}</Form.Text>}
                </Form.Group>
                <TextField label={tipo === 'ajuste' ? 'Stock real contado' : 'Cantidad'} type="number" min="0" step={pasoDeCantidad(item.unidad_venta)} inputMode="decimal"
                    value={cantidad} required error={errores.cantidad} onChange={(event) => setCantidad(event.target.value)} />
                <TextField label={tipo === 'entrada' ? 'Motivo (opcional)' : 'Motivo'} value={motivo} maxLength={200} required={tipo !== 'entrada'}
                    hint="Por ejemplo: compra a proveedor, merma o conteo mensual." error={errores.motivo} onChange={(event) => setMotivo(event.target.value)} />
                {errores.producto && <p className="text-danger" role="alert">{errores.producto}</p>}
                {errorGeneral && <p className="text-danger" role="alert">{errorGeneral}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}

