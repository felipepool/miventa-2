import axios from 'axios';
import { Badge, Button, Card, Spinner, Table } from 'react-bootstrap';
import { Link, useParams } from 'react-router-dom';
import { Icon, PageHeader } from '@/components/ui';
import { formatearFechaHora, formatearMonto } from '@/lib/caja';
import { mensajeDeError } from '@/lib/http';
import { formatearCantidad } from '@/lib/inventario';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { useTitulo } from '@/lib/useTitulo';
import type { Venta } from '@/types/venta';

export default function VentaDetalle() {
    useTitulo('Detalle de la venta');
    const { ventaId } = useParams();
    const consulta = useConsultaApi<{ data: Venta }>('/ventas/'+ventaId);
    const volver = <Link to="/ventas" className="btn btn-outline-secondary"><Icon name="arrow-left" className="me-2" />Volver al historial</Link>;

    if (consulta.error) {
        const noDisponible = axios.isAxiosError(consulta.error) && consulta.error.response?.status === 404;

        return <>
            <PageHeader title="Detalle de la venta" actions={volver} />
            <div role="alert">
                <p>{noDisponible ? 'La venta no existe o ya no está disponible.' : 'No se pudo cargar la venta. '+mensajeDeError(consulta.error)}</p>
                {!noDisponible && <Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button>}
            </div>
        </>;
    }

    if (!consulta.datos) {
        return <p role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando venta…</p>;
    }

    const venta = consulta.datos.data;

    return <>
        <PageHeader title={'Venta N° '+venta.folio} subtitle={formatearFechaHora(venta.pagada_at)+' · '+venta.vendedor} actions={volver} />
        <Card className="mb-3"><Card.Body>
            <div className="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div><div className="small text-body-secondary">Total</div><div className="fs-3 fw-semibold">{formatearMonto(venta.total)}</div></div>
                <Badge pill bg={venta.estado === 'pagada' ? 'success-subtle' : 'secondary-subtle'} text={venta.estado === 'pagada' ? 'success' : 'secondary'} className="fs-6">{venta.estado === 'pagada' ? 'Pagada' : 'Anulada'}</Badge>
            </div>
            <dl className="row mb-0 mt-3 small">
                <dt className="col-6 col-sm-3">Subtotal</dt><dd className="col-6 col-sm-3">{formatearMonto(venta.subtotal)}</dd>
                {venta.descuento > 0 && <><dt className="col-6 col-sm-3">Descuentos</dt><dd className="col-6 col-sm-3">-{formatearMonto(venta.descuento)}{venta.descuento_motivo ? ' · '+venta.descuento_motivo : ''}</dd></>}
                {venta.redondeo !== 0 && <><dt className="col-6 col-sm-3">Redondeo</dt><dd className="col-6 col-sm-3">{formatearMonto(venta.redondeo)}</dd></>}
                {venta.notas && <><dt className="col-6 col-sm-3">Notas</dt><dd className="col-6 col-sm-9">{venta.notas}</dd></>}
            </dl>
        </Card.Body></Card>

        <Card className="mb-3">
            <Card.Header>Productos</Card.Header>
            <div className="table-responsive overflow-y-hidden"><Table size="sm" className="mb-0 align-middle">
                <caption className="visually-hidden">Productos de la venta</caption>
                <thead><tr><th scope="col">Producto</th><th scope="col" className="text-end">Cantidad</th><th scope="col" className="text-end">Precio</th><th scope="col" className="text-end pe-3">Total</th></tr></thead>
                <tbody>{(venta.items ?? []).map((item, indice) => <tr key={indice}>
                    <td><strong>{item.nombre}</strong>
                        {item.modificadores.map((modificador, posicion) => <small key={posicion} className="d-block text-body-secondary">{modificador.grupo}: {modificador.nombre}{modificador.cantidad > 1 ? ' ×'+modificador.cantidad : ''}{modificador.total > 0 ? ' (+'+formatearMonto(modificador.total)+')' : ''}</small>)}
                        {item.descuento > 0 && <small className="d-block text-success">Descuento -{formatearMonto(item.descuento)}</small>}</td>
                    <td className="text-end">{formatearCantidad(item.cantidad, item.unidad_venta)}</td>
                    <td className="text-end">{formatearMonto(item.precio_unitario+item.modificadores_unitario)}</td>
                    <td className="text-end pe-3">{formatearMonto(item.total)}</td>
                </tr>)}</tbody>
            </Table></div>
        </Card>

        <Card>
            <Card.Header>Pagos</Card.Header>
            <div className="table-responsive overflow-y-hidden"><Table size="sm" className="mb-0 align-middle">
                <caption className="visually-hidden">Pagos de la venta</caption>
                <thead><tr><th scope="col">Medio</th><th scope="col" className="text-end">Monto</th><th scope="col" className="text-end">Recibido</th><th scope="col" className="text-end pe-3">Vuelto</th></tr></thead>
                <tbody>{(venta.pagos ?? []).map((pago, indice) => <tr key={indice}>
                    <td>{pago.etiqueta}</td><td className="text-end">{formatearMonto(pago.monto)}</td>
                    <td className="text-end">{pago.recibido === null ? '—' : formatearMonto(pago.recibido)}</td><td className="text-end pe-3">{pago.vuelto > 0 ? formatearMonto(pago.vuelto) : '—'}</td>
                </tr>)}</tbody>
            </Table></div>
        </Card>
    </>;
}
