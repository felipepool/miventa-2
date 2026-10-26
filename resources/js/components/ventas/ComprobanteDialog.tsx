import { useState } from 'react';
import { Button, ButtonGroup, Spinner } from 'react-bootstrap';
import TicketComprobante from '@/components/ventas/TicketComprobante';
import { FormDialog, Icon } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import { mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Comprobante, ComprobanteConEnlace } from '@/types/venta';

interface ComprobanteDialogProps {
    ventaId: string;
    onHide: () => void;
}

/** Resumen corto de la venta para enviarlo por WhatsApp junto al enlace. */
function textoParaCompartir(comprobante: Comprobante): string {
    return comprobante.negocio.nombre+' · Venta N° '+comprobante.folio+' por '+formatearMonto(comprobante.total)+'. Revisa tu comprobante:';
}

/**
 * Comprobante de una venta: vista previa del ticket para imprimir en papel de 80 o 58 mm y un
 * enlace público, válido por 30 días, para compartirlo con el cliente.
 */
export default function ComprobanteDialog({ ventaId, onHide }: ComprobanteDialogProps) {
    const consulta = useConsultaApi<{ data: ComprobanteConEnlace }>('/ventas/'+ventaId+'/enlace-comprobante');
    const [ancho, setAncho] = useState<80 | 58>(80);
    const datos = consulta.datos?.data;
    const puedeCompartir = typeof navigator.share === 'function';

    const compartir = async (comprobante: Comprobante, enlace: string) => {
        try {
            await navigator.share({ title: 'Comprobante de venta', text: textoParaCompartir(comprobante), url: enlace });
        } catch (error) {
            if (!(error instanceof DOMException && error.name === 'AbortError')) {
                notificar('No se pudo abrir el menú para compartir.', 'error');
            }
        }
    };

    const copiar = async (enlace: string) => {
        try {
            await navigator.clipboard.writeText(enlace);
            notificar('Enlace copiado.');
        } catch {
            notificar('No se pudo copiar el enlace.', 'error');
        }
    };

    const acciones = datos ? <>
        {puedeCompartir
            ? <Button variant="outline-secondary" onClick={() => void compartir(datos.comprobante, datos.enlace)}><Icon name="share" className="me-2" />Compartir</Button>
            : <>
                <a className="btn btn-outline-secondary d-inline-flex align-items-center" target="_blank" rel="noopener noreferrer"
                    href={'https://wa.me/?text='+encodeURIComponent(textoParaCompartir(datos.comprobante)+' '+datos.enlace)}><Icon name="whatsapp" className="me-2" />WhatsApp</a>
                <Button variant="outline-secondary" onClick={() => void copiar(datos.enlace)}><Icon name="link-45deg" className="me-2" />Copiar enlace</Button>
            </>}
        <Button onClick={() => window.print()}><Icon name="printer" className="me-2" />Imprimir</Button>
    </> : <Button variant="outline-secondary" onClick={onHide}>Cerrar</Button>;

    return <FormDialog title="Comprobante" size="md" onHide={onHide} actions={acciones}>
        {consulta.error ? <div role="alert">
            <p>No se pudo cargar el comprobante. {mensajeDeError(consulta.error)}</p>
            <Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button>
        </div> : !datos ? <p role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando comprobante…</p> : <>
            <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <span className="small text-body-secondary">Papel de la impresora</span>
                <ButtonGroup aria-label="Ancho del papel">
                    {([80, 58] as const).map((opcion) => <Button key={opcion} size="sm" variant={ancho === opcion ? 'primary' : 'outline-secondary'} aria-pressed={ancho === opcion}
                        onClick={() => setAncho(opcion)}>{opcion} mm</Button>)}
                </ButtonGroup>
            </div>
            <div className="ticket-vista"><TicketComprobante comprobante={datos.comprobante} ancho={ancho} /></div>
            <p className="small text-body-secondary mt-3 mb-0">El enlace para compartir funciona sin iniciar sesión durante 30 días.</p>
        </>}
    </FormDialog>;
}
