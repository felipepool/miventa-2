import { Badge, Card, Col, Row, Table } from 'react-bootstrap';
import { describirDiferencia, formatearFechaHora, formatearMonto } from '@/lib/caja';
import type { TurnoCaja } from '@/types/turno';

function Cifra({ etiqueta, valor, destacada = false }: { etiqueta: string; valor: string; destacada?: boolean }) {
    return <Col xs={6} md={3}>
        <div className="small text-body-secondary">{etiqueta}</div>
        <div className={destacada ? 'fs-5 fw-semibold' : 'fw-semibold'}>{valor}</div>
    </Col>;
}

/** Cifras del turno, billetes y monedas contados y movimientos. Sirve de comprobante al cerrar. */
export default function ResumenTurno({ turno }: { turno: TurnoCaja }) {
    const cerrado = turno.cerrada_at !== null;
    const diferencia = turno.diferencia;

    return <>
        <Card className="mb-3">
            <Card.Body>
                <Row className="g-3">
                    <Cifra etiqueta="Monto inicial" valor={formatearMonto(turno.monto_inicial)} />
                    <Cifra etiqueta="Ingresos" valor={formatearMonto(turno.ingresos)} />
                    <Cifra etiqueta="Retiros" valor={formatearMonto(turno.retiros)} />
                    <Cifra etiqueta="Ventas en efectivo" valor={formatearMonto(turno.ventas_efectivo)} />
                    {turno.devoluciones_efectivo > 0 && <Cifra etiqueta="Devoluciones en efectivo" valor={'-'+formatearMonto(turno.devoluciones_efectivo)} />}
                    {turno.monto_esperado !== undefined && turno.monto_esperado !== null && <Cifra etiqueta="Efectivo esperado" valor={formatearMonto(turno.monto_esperado)} destacada />}
                    {cerrado && turno.monto_contado !== null && <Cifra etiqueta="Efectivo contado" valor={formatearMonto(turno.monto_contado)} destacada />}
                    {cerrado && diferencia !== undefined && diferencia !== null && <Col xs={6} md={3}>
                        <div className="small text-body-secondary">Diferencia</div>
                        <Badge pill bg={diferencia === 0 ? 'success-subtle' : 'warning-subtle'} text={diferencia === 0 ? 'success' : 'warning-emphasis'} className="fs-6">{describirDiferencia(diferencia)}</Badge>
                    </Col>}
                </Row>
                {cerrado && turno.monto_esperado === undefined && <p className="small text-body-secondary mt-3 mb-0">Cierre ciego: el esperado y la diferencia los revisa el administrador.</p>}
                {turno.nota_apertura && <p className="small mt-3 mb-0"><strong>Nota de apertura:</strong> {turno.nota_apertura}</p>}
                {turno.nota_cierre && <p className="small mt-2 mb-0"><strong>Nota de cierre:</strong> {turno.nota_cierre}</p>}
            </Card.Body>
        </Card>

        {turno.conteo && turno.conteo.length > 0 && <Card className="mb-3">
            <Card.Header>Billetes y monedas contados</Card.Header>
            <Table size="sm" className="mb-0 align-middle">
                <caption className="visually-hidden">Conteo del efectivo</caption>
                <thead><tr><th scope="col">Denominación</th><th scope="col" className="text-end">Cantidad</th><th scope="col" className="text-end">Subtotal</th></tr></thead>
                <tbody>{turno.conteo.map((fila) => <tr key={fila.valor}>
                    <td>{formatearMonto(fila.valor)}</td><td className="text-end">{fila.cantidad}</td><td className="text-end">{formatearMonto(fila.valor*fila.cantidad)}</td>
                </tr>)}</tbody>
            </Table>
        </Card>}

        <Card>
            <Card.Header>Movimientos de efectivo</Card.Header>
            {turno.movimientos && turno.movimientos.length > 0 ? <div className="table-responsive overflow-y-hidden"><Table size="sm" className="mb-0 align-middle">
                <caption className="visually-hidden">Ingresos y retiros del turno</caption>
                <thead><tr><th scope="col">Hora</th><th scope="col">Tipo</th><th scope="col" className="text-end pe-4">Monto</th><th scope="col">Motivo</th><th scope="col">Usuario</th></tr></thead>
                <tbody>{turno.movimientos.map((movimiento) => <tr key={movimiento.id}>
                    <td>{formatearFechaHora(movimiento.created_at)}</td>
                    <td><Badge pill bg={movimiento.tipo === 'ingreso' ? 'success-subtle' : 'danger-subtle'} text={movimiento.tipo === 'ingreso' ? 'success' : 'danger'}>{movimiento.tipo === 'ingreso' ? 'Ingreso' : 'Retiro'}</Badge></td>
                    <td className="text-end pe-4">{movimiento.tipo === 'retiro' ? '-' : '+'}{formatearMonto(movimiento.monto)}</td>
                    <td>{movimiento.motivo}</td><td>{movimiento.usuario ?? '—'}</td>
                </tr>)}</tbody>
            </Table></div> : <Card.Body className="text-body-secondary">Este turno no tiene ingresos ni retiros.</Card.Body>}
        </Card>
    </>;
}

