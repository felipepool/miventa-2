import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from 'react-bootstrap';
import { FormDialog, TextField } from '@/components/ui';
import { ETIQUETAS_UNIDAD } from '@/lib/inventario';
import type { ProductoPos } from '@/types/venta';

interface CantidadDialogProps {
    producto: ProductoPos;
    onAgregar: (cantidad: string) => void;
    onHide: () => void;
}

/** Cantidad de un producto que se vende por peso, volumen o largo (ej. 0,318 kg). */
export default function CantidadDialog({ producto, onAgregar, onHide }: CantidadDialogProps) {
    const [cantidad, setCantidad] = useState('1');
    const valida = Number(cantidad) > 0 && /^\d{1,5}(\.\d{1,3})?$/.test(cantidad);

    const confirmar = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (valida) { onAgregar(cantidad); }
    };

    return <FormDialog title={producto.nombre} size="md" onHide={onHide} actions={<>
        <Button variant="outline-secondary" onClick={onHide}>Cancelar</Button>
        <Button type="submit" form="cantidad-form" disabled={!valida}>Agregar</Button>
    </>}>
        <form id="cantidad-form" noValidate onSubmit={confirmar}>
            <TextField label={'Cantidad ('+(ETIQUETAS_UNIDAD[producto.unidad_venta] ?? producto.unidad_venta)+')'} type="number" min="0" step="0.001" inputMode="decimal" autoFocus value={cantidad}
                error={cantidad !== '' && !valida ? 'Usa una cantidad mayor que cero, con hasta 3 decimales.' : undefined} onChange={(event) => setCantidad(event.target.value)} />
        </form>
    </FormDialog>;
}
