import { useId } from 'react';
import { Alert, Form } from 'react-bootstrap';
import type { CobroSucursal } from '@/types/sucursal';

export const formatoUf = (precio: string) => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 4 }).format(Number(precio));

interface AvisoCobroProps {
    cobro: CobroSucursal;
    aceptado: boolean;
    onChange: (aceptado: boolean) => void;
    disabled?: boolean;
    error?: string;
}

export default function AvisoCobro({ cobro, aceptado, onChange, disabled = false, error }: AvisoCobroProps) {
    const id = useId();

    return (
        <>
            <Alert variant={cobro.requiere_aceptacion ? 'warning' : 'info'} className="small mb-2">
                <p className="fw-semibold mb-1">{cobro.requiere_aceptacion ? 'Costo mensual adicional' : 'Incluida en tu plan'}</p>
                <p className="mb-1">Tu plan incluye una sucursal. Cada sucursal incluye su primera caja sin otro recargo.</p>
                {cobro.total_mensual_uf === null ? (
                    <p className="mb-0">La tarifa no está configurada. Contacta a soporte antes de contratar.</p>
                ) : cobro.requiere_aceptacion ? (
                    <>
                        {cobro.genera_cobro && cobro.precio_sucursal_uf !== null && <p className="mb-1">Sucursal adicional: <strong>{formatoUf(cobro.precio_sucursal_uf)} UF/mes + IVA.</strong></p>}
                        {cobro.cajas_adicionales > 0 && cobro.precio_caja_uf !== null && <p className="mb-1">{cobro.cajas_adicionales} {cobro.cajas_adicionales === 1 ? 'caja adicional' : 'cajas adicionales'}: {formatoUf(cobro.precio_caja_uf)} UF/mes + IVA cada una.</p>}
                        {cobro.cajas_adicionales > 0 && <p className="mb-1">Total adicional: <strong>{formatoUf(cobro.total_mensual_uf)} UF/mes + IVA.</strong></p>}
                        <p className="mb-1">Este mes: {formatoUf(cobro.este_mes.monto_uf!)} UF + IVA por los {cobro.este_mes.dias} días restantes.</p>
                        <p className="mb-0">Al desactivarla deja de cobrarse desde ese día. El precio aceptado se conserva mientras siga contratada.</p>
                    </>
                ) : <p className="mb-0">Esta sucursal y su primera caja no generan un costo adicional.</p>}
            </Alert>
            {cobro.requiere_aceptacion && cobro.total_mensual_uf !== null && (
                <Form.Group>
                    <Form.Check id={id} className="contract-acceptance" checked={aceptado} disabled={disabled} isInvalid={Boolean(error)}
                        label="Acepto el cobro mensual adicional indicado."
                        aria-invalid={error ? true : undefined} aria-describedby={error ? id+'-error' : undefined}
                        onChange={(event) => onChange(event.target.checked)} />
                    {error && <div id={id+'-error'} className="invalid-feedback d-block">{error}</div>}
                </Form.Group>
            )}
        </>
    );
}
