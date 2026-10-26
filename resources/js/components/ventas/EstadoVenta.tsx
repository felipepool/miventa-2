import { Badge } from 'react-bootstrap';
import type { Venta } from '@/types/venta';

/** Etiqueta del estado de una venta: anulada, devuelta, con devolución o pagada. */
export default function EstadoVenta({ venta, className }: { venta: Pick<Venta, 'estado' | 'devolucion'>; className?: string }) {
    const [texto, color] = venta.estado === 'anulada' ? ['Anulada', 'danger'] : venta.devolucion === 'total' ? ['Devuelta', 'secondary']
        : venta.devolucion === 'parcial' ? ['Con devolución', 'warning'] : ['Pagada', 'success'];

    return <Badge pill bg={color+'-subtle'} text={color === 'warning' ? 'dark' : color} className={className}>{texto}</Badge>;
}
