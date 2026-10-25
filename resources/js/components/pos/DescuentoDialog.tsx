import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Form } from 'react-bootstrap';
import { FormDialog, TextField } from '@/components/ui';
import type { Descuento } from '@/types/venta';

interface DescuentoDialogProps {
    titulo: string;
    actual: Descuento | null;
    motivoActual: string;
    /** Porcentaje máximo del cajero; `null` si puede descontar sin límite. */
    tope: number | null;
    onGuardar: (descuento: Descuento | null, motivo: string) => void;
    onHide: () => void;
}

/** Descuento por porcentaje o monto, con su motivo, para una línea o para toda la venta. */
export default function DescuentoDialog({ titulo, actual, motivoActual, tope, onGuardar, onHide }: DescuentoDialogProps) {
    const [tipo, setTipo] = useState<Descuento['tipo']>(actual?.tipo ?? 'porcentaje');
    const [valor, setValor] = useState(actual ? String(actual.valor) : '');
    const [motivo, setMotivo] = useState(motivoActual);
    const numero = Number(valor);
    const valorValido = Number.isInteger(numero) && numero >= 1 && (tipo !== 'porcentaje' || numero <= 100);
    const valida = valorValido && motivo.trim() !== '';

    const guardar = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (valida) { onGuardar({ tipo, valor: numero }, motivo.trim()); }
    };

    return <FormDialog title={titulo} size="md" onHide={onHide} actions={<>
        {actual && <Button variant="outline-danger" className="me-auto" onClick={() => onGuardar(null, motivo)}>Quitar descuento</Button>}
        <Button variant="outline-secondary" onClick={onHide}>Cancelar</Button>
        <Button type="submit" form="descuento-form" disabled={!valida}>Aplicar</Button>
    </>}>
        <form id="descuento-form" noValidate onSubmit={guardar}>
            <Form.Group controlId="descuento-tipo" className="mb-3"><Form.Label>Tipo de descuento</Form.Label>
                <Form.Select value={tipo} onChange={(event) => setTipo(event.target.value as Descuento['tipo'])}>
                    <option value="porcentaje">Porcentaje (%)</option><option value="monto">Monto (CLP)</option>
                </Form.Select>
            </Form.Group>
            <TextField label={tipo === 'porcentaje' ? 'Porcentaje' : 'Monto a descontar (CLP)'} type="number" min="1" step="1" inputMode="numeric" autoFocus value={valor}
                error={valor !== '' && !valorValido ? (tipo === 'porcentaje' ? 'Usa un porcentaje entero entre 1 y 100.' : 'Usa un monto entero mayor que cero.') : undefined}
                hint={tope === null ? 'Puedes descontar sin límite.' : 'Tu tope es '+tope+'% de la venta; un descuento mayor lo rechaza el sistema.'} onChange={(event) => setValor(event.target.value)} />
            <TextField label="Motivo" value={motivo} maxLength={150} required hint="Por ejemplo: cliente frecuente o producto dañado." onChange={(event) => setMotivo(event.target.value)} />
        </form>
    </FormDialog>;
}
