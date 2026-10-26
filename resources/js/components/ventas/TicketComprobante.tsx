import { formatearFechaHora, formatearMonto } from '@/lib/caja';
import { formatearCantidad } from '@/lib/inventario';
import type { Comprobante } from '@/types/venta';

interface TicketComprobanteProps {
    comprobante: Comprobante;
    /** Ancho del papel de la impresora térmica. */
    ancho: 80 | 58;
}

function Fila({ etiqueta, valor, destacada = false }: { etiqueta: string; valor: string; destacada?: boolean }) {
    return <div className={'ticket__fila'+(destacada ? ' ticket__fila--total' : '')}><span>{etiqueta}</span><span>{valor}</span></div>;
}

/**
 * Ticket del comprobante con el ancho del papel térmico. Al imprimir solo se ve el ticket
 * (ver `.ticket` en los estilos de impresión).
 */
export default function TicketComprobante({ comprobante, ancho }: TicketComprobanteProps) {
    const devuelto = comprobante.devoluciones.reduce((suma, devolucion) => suma+devolucion.monto, 0);

    return <article className={'ticket ticket--'+ancho} aria-label={'Comprobante de la venta N° '+comprobante.folio}>
        <header className="ticket__encabezado">
            <strong className="ticket__negocio">{comprobante.negocio.nombre}</strong>
            {comprobante.negocio.rut && <span>RUT {comprobante.negocio.rut}</span>}
            <span>{comprobante.sucursal}</span>
            {comprobante.negocio.direccion && <span>{comprobante.negocio.direccion}</span>}
            {comprobante.negocio.telefono && <span>Tel. {comprobante.negocio.telefono}</span>}
        </header>
        <div className="ticket__separador" />
        <Fila etiqueta={'Venta N° '+comprobante.folio} valor={formatearFechaHora(comprobante.fecha)} />
        <Fila etiqueta="Atendió" valor={comprobante.cajero} />
        {comprobante.estado === 'anulada' && <p className="ticket__sello" role="status">ANULADA{comprobante.anulada_at ? ' · '+formatearFechaHora(comprobante.anulada_at) : ''}</p>}
        <div className="ticket__separador" />
        <ul className="ticket__items">
            {comprobante.items.map((item, indice) => <li key={indice}>
                <span className="ticket__nombre">{item.nombre}</span>
                {item.modificadores.map((modificador, posicion) => <span key={posicion} className="ticket__detalle">+ {modificador.nombre}{modificador.cantidad > 1 ? ' ×'+modificador.cantidad : ''}</span>)}
                <div className="ticket__fila">
                    <span>{formatearCantidad(item.cantidad, item.unidad_venta)} × {formatearMonto(item.precio_unitario)}</span>
                    <span>{formatearMonto(item.subtotal)}</span>
                </div>
                {item.descuento > 0 && <div className="ticket__fila ticket__detalle"><span>Descuento</span><span>-{formatearMonto(item.descuento)}</span></div>}
            </li>)}
        </ul>
        <div className="ticket__separador" />
        {comprobante.descuento > 0 && <Fila etiqueta="Descuentos" valor={'-'+formatearMonto(comprobante.descuento)} />}
        {comprobante.redondeo !== 0 && <Fila etiqueta="Redondeo" valor={formatearMonto(comprobante.redondeo)} />}
        <Fila etiqueta="TOTAL" valor={formatearMonto(comprobante.total)} destacada />
        {comprobante.pagos.map((pago, indice) => <div key={indice}>
            <Fila etiqueta={pago.etiqueta} valor={formatearMonto(pago.recibido ?? pago.monto)} />
            {pago.vuelto > 0 && <Fila etiqueta="Vuelto" valor={formatearMonto(pago.vuelto)} />}
        </div>)}
        {comprobante.devoluciones.length > 0 && <>
            <div className="ticket__separador" />
            <strong className="ticket__titulo">Devoluciones</strong>
            {comprobante.devoluciones.map((devolucion, indice) => <div key={indice} className="ticket__devolucion">
                <Fila etiqueta={formatearFechaHora(devolucion.fecha)} valor={'-'+formatearMonto(devolucion.monto)} />
                <span className="ticket__detalle">{devolucion.etiqueta} · {devolucion.items.map((item) => item.nombre+' '+formatearCantidad(item.cantidad, item.unidad_venta)).join(', ')}</span>
            </div>)}
            <Fila etiqueta="Total devuelto" valor={'-'+formatearMonto(devuelto)} />
        </>}
        <div className="ticket__separador" />
        <p className="ticket__pie">Comprobante interno, no válido como boleta.</p>
    </article>;
}
