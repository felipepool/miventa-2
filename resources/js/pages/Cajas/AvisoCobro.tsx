import { useId } from 'react';
import { Alert, Form } from 'react-bootstrap';
import type { CobroCaja } from '@/types/caja';

export const formatoUf = (precio: string) => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 4 }).format(Number(precio));

interface AvisoCobroProps {
    cobro: CobroCaja;
    aceptado: boolean;
    onChange: (aceptado: boolean) => void;
    disabled?: boolean;
    error?: string;
}

export default function AvisoCobro({ cobro, aceptado, onChange, disabled = false, error }: AvisoCobroProps) {
    const id = useId();
    return <>
        <Alert variant={cobro.requiere_aceptacion ? 'warning' : 'info'} className="small mb-2">
            <p className="fw-semibold mb-1">{cobro.requiere_aceptacion ? 'Caja adicional' : 'Caja incluida'}</p>
            {cobro.total_mensual_uf === null ? <p className="mb-0">La tarifa no está configurada. Contacta a soporte antes de contratar.</p>
                : cobro.requiere_aceptacion ? <>
                    <p className="mb-1">Esta sucursal ya tiene una caja activa incluida. Esta caja cuesta <strong>{formatoUf(cobro.total_mensual_uf)} UF/mes + IVA.</strong></p>
                    <p className="mb-1">Este mes: {formatoUf(cobro.este_mes.monto_uf!)} UF + IVA por los {cobro.este_mes.dias} días restantes.</p>
                    <p className="mb-0">El precio aceptado se conserva mientras siga contratada. Al desactivarla deja de cobrarse desde ese día.</p>
                </> : <p className="mb-0">Cada sucursal incluye una caja activa. Esta caja no genera un costo adicional.</p>}
        </Alert>
        {cobro.requiere_aceptacion && cobro.total_mensual_uf !== null && <Form.Group>
            <Form.Check id={id} className="contract-acceptance" checked={aceptado} disabled={disabled} isInvalid={Boolean(error)}
                label="Acepto el cobro mensual adicional indicado." aria-invalid={error ? true : undefined}
                aria-describedby={error ? id+'-error' : undefined} onChange={(event) => onChange(event.target.checked)} />
            {error && <div id={id+'-error'} className="invalid-feedback d-block">{error}</div>}
        </Form.Group>}
    </>;
}
