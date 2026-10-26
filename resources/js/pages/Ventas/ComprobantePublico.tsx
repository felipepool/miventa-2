import axios from 'axios';
import { Button, Spinner } from 'react-bootstrap';
import { useLocation, useParams } from 'react-router-dom';
import TicketComprobante from '@/components/ventas/TicketComprobante';
import { Icon } from '@/components/ui';
import { mensajeDeError } from '@/lib/http';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { useTitulo } from '@/lib/useTitulo';
import type { Comprobante } from '@/types/venta';

/**
 * Comprobante abierto desde el enlace que comparte el negocio. No pide sesión: la API solo
 * responde si la firma del enlace es válida y no venció.
 */
export default function ComprobantePublico() {
    useTitulo('Comprobante de venta');
    const { ventaId } = useParams();
    const { search } = useLocation();
    const consulta = useConsultaApi<{ data: Comprobante }>('/comprobantes/'+ventaId+search);
    const vencido = axios.isAxiosError(consulta.error) && consulta.error.response?.status === 403;

    return <main className="comprobante-publico">
        {consulta.error ? <div className="comprobante-publico__aviso" role="alert">
            <Icon name="receipt" className="fs-1 text-body-secondary d-block mb-3" />
            <h1 className="h5">{vencido ? 'Este enlace ya no es válido' : 'No se pudo cargar el comprobante'}</h1>
            <p className="text-body-secondary mb-3">{vencido ? 'El enlace venció o está incompleto. Pide al negocio que te lo envíe de nuevo.' : mensajeDeError(consulta.error)}</p>
            {!vencido && <Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button>}
        </div> : !consulta.datos ? <p role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando comprobante…</p> : <>
            <TicketComprobante comprobante={consulta.datos.data} ancho={80} />
            <Button variant="outline-secondary" className="comprobante-publico__imprimir" onClick={() => window.print()}><Icon name="printer" className="me-2" />Imprimir o guardar en PDF</Button>
        </>}
    </main>;
}
