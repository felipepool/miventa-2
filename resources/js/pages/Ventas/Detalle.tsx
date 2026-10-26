import axios from 'axios';
import { useState } from 'react';
import { Alert, Button, Card, Spinner, Table } from 'react-bootstrap';
import { Link, useParams } from 'react-router-dom';
import AnularVentaDialog from '@/components/ventas/AnularVentaDialog';
import ComprobanteDialog from '@/components/ventas/ComprobanteDialog';
import DevolucionDialog from '@/components/ventas/DevolucionDialog';
import EstadoVenta from '@/components/ventas/EstadoVenta';
import { EditDialog, FormDialog, Icon, PageHeader } from '@/components/ui';
import { useSesion } from '@/lib/auth';
import { formatearFechaHora, formatearMonto } from '@/lib/caja';
import { mensajeDeError } from '@/lib/http';
import { formatearCantidad } from '@/lib/inventario';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { useTitulo } from '@/lib/useTitulo';
import type { MedioPago, Venta } from '@/types/venta';

type Dialogo = 'anular' | 'devolver' | 'comprobante';

export default function VentaDetalle() {
    useTitulo('Detalle de la venta');
    const { ventaId } = useParams();
    const { permisos } = useSesion();
    const consulta = useConsultaApi<{ data: Venta; medios_pago: { value: MedioPago; label: string }[] }>('/ventas/'+ventaId);
    const [actualizada, setActualizada] = useState<Venta | null>(null);
    const [dialogo, setDialogo] = useState<Dialogo | null>(null);
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

    const venta = actualizada ?? consulta.datos.data;
    const medios = consulta.datos.medios_pago;
    const corrige = permisos.includes('anular_ventas') && venta.estado === 'pagada';
    const puedeAnular = corrige && venta.turno_abierto === true && (venta.devoluciones ?? []).length === 0;
    const puedeDevolver = corrige && venta.devolucion !== 'total';
    const alCorregir = (corregida: Venta, mensaje: string) => { setActualizada(corregida); setDialogo(null); notificar(mensaje); };

    return <>
        <PageHeader title={'Venta N° '+venta.folio} subtitle={formatearFechaHora(venta.pagada_at)+' · '+venta.vendedor} actions={<>
            {volver}
            <Button variant="outline-secondary" onClick={() => setDialogo('comprobante')}><Icon name="receipt" className="me-2" />Comprobante</Button>
            {puedeDevolver && <Button variant="outline-secondary" onClick={() => setDialogo('devolver')}><Icon name="arrow-counterclockwise" className="me-2" />Devolver</Button>}
            {puedeAnular && <Button variant="outline-danger" onClick={() => setDialogo('anular')}><Icon name="x-circle" className="me-2" />Anular</Button>}
        </>} />

        {venta.estado === 'anulada' && <Alert variant="danger">
            <strong>Venta anulada</strong>{venta.anulada_at ? ' el '+formatearFechaHora(venta.anulada_at) : ''}{venta.anulada_por ? ' por '+venta.anulada_por : ''}.
            {venta.motivo_anulacion && <> Motivo: {venta.motivo_anulacion}.</>}
        </Alert>}
        {corrige && !puedeAnular && venta.devolucion !== 'total' && <p className="small text-body-secondary">
            {venta.turno_abierto === false ? 'El turno de esta venta ya se cerró: para corregirla, registra una devolución.' : 'Esta venta tiene devoluciones: para corregirla, registra otra devolución.'}
        </p>}

        <Card className="mb-3"><Card.Body>
            <div className="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div><div className="small text-body-secondary">Total</div><div className="fs-3 fw-semibold">{formatearMonto(venta.total)}</div></div>
                <EstadoVenta venta={venta} className="fs-6" />
            </div>
            <dl className="row mb-0 mt-3 small">
                <dt className="col-6 col-sm-3">Subtotal</dt><dd className="col-6 col-sm-3">{formatearMonto(venta.subtotal)}</dd>
                {venta.descuento > 0 && <><dt className="col-6 col-sm-3">Descuentos</dt><dd className="col-6 col-sm-3">-{formatearMonto(venta.descuento)}{venta.descuento_motivo ? ' · '+venta.descuento_motivo : ''}</dd></>}
                {venta.redondeo !== 0 && <><dt className="col-6 col-sm-3">Redondeo</dt><dd className="col-6 col-sm-3">{formatearMonto(venta.redondeo)}</dd></>}
                {(venta.total_devuelto ?? 0) > 0 && <><dt className="col-6 col-sm-3">Devuelto</dt><dd className="col-6 col-sm-3">-{formatearMonto(venta.total_devuelto ?? 0)}</dd></>}
                {venta.notas && <><dt className="col-6 col-sm-3">Notas</dt><dd className="col-6 col-sm-9">{venta.notas}</dd></>}
            </dl>
        </Card.Body></Card>

        <Card className="mb-3">
            <Card.Header>Productos</Card.Header>
            <div className="table-responsive overflow-y-hidden"><Table size="sm" className="mb-0 align-middle">
                <caption className="visually-hidden">Productos de la venta</caption>
                <thead><tr><th scope="col">Producto</th><th scope="col" className="text-end">Cantidad</th><th scope="col" className="text-end">Precio</th><th scope="col" className="text-end pe-3">Total</th></tr></thead>
                <tbody>{(venta.items ?? []).map((item) => <tr key={item.linea}>
                    <td><strong>{item.nombre}</strong>
                        {item.modificadores.map((modificador, posicion) => <small key={posicion} className="d-block text-body-secondary">{modificador.grupo}: {modificador.nombre}{modificador.cantidad > 1 ? ' ×'+modificador.cantidad : ''}{modificador.total > 0 ? ' (+'+formatearMonto(modificador.total)+')' : ''}</small>)}
                        {item.descuento > 0 && <small className="d-block text-success">Descuento -{formatearMonto(item.descuento)}</small>}
                        {Number(item.devuelta) > 0 && <small className="d-block text-warning-emphasis">Devuelto {formatearCantidad(item.devuelta ?? '0', item.unidad_venta)}</small>}</td>
                    <td className="text-end">{formatearCantidad(item.cantidad, item.unidad_venta)}</td>
                    <td className="text-end">{formatearMonto(item.precio_unitario+item.modificadores_unitario)}</td>
                    <td className="text-end pe-3">{formatearMonto(item.total)}</td>
                </tr>)}</tbody>
            </Table></div>
        </Card>

        <Card className="mb-3">
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

        {(venta.devoluciones ?? []).length > 0 && <Card>
            <Card.Header>Devoluciones</Card.Header>
            <ul className="list-group list-group-flush">
                {(venta.devoluciones ?? []).map((devolucion) => <li key={devolucion.id} className="list-group-item">
                    <div className="d-flex flex-wrap justify-content-between gap-2">
                        <strong>{formatearFechaHora(devolucion.created_at)} · {devolucion.usuario}</strong>
                        <strong>-{formatearMonto(devolucion.monto)} · {devolucion.etiqueta}</strong>
                    </div>
                    <small className="d-block text-body-secondary">{devolucion.items.map((item) => item.nombre+' '+formatearCantidad(item.cantidad, item.unidad_venta)).join(', ')}</small>
                    <small className="d-block text-body-secondary">Motivo: {devolucion.motivo}{devolucion.reintegra_stock ? '' : ' · No volvió al stock'}</small>
                </li>)}
            </ul>
        </Card>}

        {(dialogo === 'anular' || dialogo === 'devolver') && <EditDialog<Venta> key={dialogo} route={'/ventas/'+venta.id}
            title={dialogo === 'anular' ? 'Anular venta' : 'Devolver productos'} size={dialogo === 'anular' ? 'md' : 'lg'}
            loadingLabel="Cargando venta…" unavailableMessage="La venta no existe o ya no está disponible."
            errorMessage="No se pudo cargar la venta." onHide={() => setDialogo(null)}>
            {(reciente) => {
                const impedimento = reciente.estado !== 'pagada' ? 'Esta venta está anulada.'
                    : dialogo === 'anular' && (!reciente.turno_abierto || (reciente.devoluciones ?? []).length > 0)
                        ? 'Esta venta ya no se puede anular. Registra una devolución.'
                        : reciente.devolucion === 'total' ? 'Todos los productos de esta venta ya fueron devueltos.' : null;

                if (impedimento) {
                    return <FormDialog title="Venta no disponible para esta operación" onHide={() => setDialogo(null)}
                        actions={<Button variant="outline-secondary" onClick={() => { setActualizada(reciente); setDialogo(null); }}>Volver</Button>}>
                        <p role="alert">{impedimento}</p>
                    </FormDialog>;
                }

                return dialogo === 'anular'
                    ? <AnularVentaDialog venta={reciente} onHide={() => setDialogo(null)} onAnulada={(anulada) => alCorregir(anulada, 'Venta anulada correctamente.')} />
                    : <DevolucionDialog venta={reciente} medios={medios} onHide={() => setDialogo(null)}
                        onDevuelta={(devuelta) => alCorregir(devuelta, 'Devolución registrada correctamente.')} />;
            }}
        </EditDialog>}
        {dialogo === 'comprobante' && <ComprobanteDialog ventaId={venta.id} onHide={() => setDialogo(null)} />}
    </>;
}
