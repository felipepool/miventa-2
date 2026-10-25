import { Button, Form } from 'react-bootstrap';
import { Icon } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import type { ResumenCarrito } from '@/lib/venta';
import type { Descuento, LineaCarrito } from '@/types/venta';

interface CarritoPanelProps {
    lineas: LineaCarrito[];
    resumen: ResumenCarrito;
    descuentoVenta: Descuento | null;
    onCantidad: (clave: string, cantidad: string) => void;
    onQuitar: (clave: string) => void;
    onDescuentoLinea: (clave: string) => void;
    onDescuentoVenta: () => void;
    onVaciar: () => void;
    onCobrar: () => void;
}

/** Nombres de los modificadores elegidos en una línea, con sus cantidades. */
function nombresDeModificadores(linea: LineaCarrito): string {
    return linea.modificadores.flatMap((elegido) => {
        const opcion = linea.producto.grupos_modificadores.flatMap((grupo) => grupo.modificadores).find((candidata) => candidata.id === elegido.id);

        return opcion ? [opcion.nombre+(elegido.cantidad > 1 ? ' ×'+elegido.cantidad : '')] : [];
    }).join(', ');
}

/** Carrito de la venta: líneas con su cantidad y descuento, totales y el botón de cobrar. */
export default function CarritoPanel({ lineas, resumen, descuentoVenta, onCantidad, onQuitar, onDescuentoLinea, onDescuentoVenta, onVaciar, onCobrar }: CarritoPanelProps) {
    if (lineas.length === 0) {
        return <div className="pos-carrito"><div className="pos-carrito__vacio text-body-secondary" role="status"><Icon name="cart" className="fs-1 d-block mb-2" />Agrega productos para empezar la venta.</div></div>;
    }

    return <div className="pos-carrito">
        <ul className="pos-carrito__lineas list-unstyled mb-0">
            {lineas.map((linea, indice) => {
                const calculo = resumen.lineas[indice];
                const medido = linea.producto.unidad_venta !== 'unidad';
                const modificadores = nombresDeModificadores(linea);

                return <li key={linea.clave} className="p-3 border-bottom">
                    <div className="d-flex justify-content-between gap-2">
                        <div className="min-w-0">
                            <strong className="d-block text-break">{linea.producto.nombre}</strong>
                            {modificadores && <small className="d-block text-body-secondary">{modificadores}</small>}
                            {linea.descuento && <small className="d-block text-success">Descuento {linea.descuento.tipo === 'porcentaje' ? linea.descuento.valor+'%' : formatearMonto(linea.descuento.valor)}</small>}
                        </div>
                        <div className="text-end flex-shrink-0">
                            <strong>{formatearMonto(calculo?.total ?? 0)}</strong>
                            {calculo && calculo.descuento > 0 && <small className="d-block text-body-secondary text-decoration-line-through">{formatearMonto(calculo.subtotal)}</small>}
                        </div>
                    </div>
                    <div className="d-flex align-items-center justify-content-between gap-2 mt-2">
                        {medido
                            ? <Form.Control type="number" min="0.001" step="0.001" inputMode="decimal" size="sm" style={{ maxWidth: '7rem' }} value={linea.cantidad} aria-label={'Cantidad de '+linea.producto.nombre}
                                onChange={(event) => onCantidad(linea.clave, event.target.value)} />
                            : <div className="d-flex align-items-center gap-2">
                                <Button variant="outline-secondary" size="sm" aria-label={'Quitar una unidad de '+linea.producto.nombre} onClick={() => onCantidad(linea.clave, String(Number(linea.cantidad)-1))}><Icon name="dash-lg" /></Button>
                                <span className="text-center" style={{ minWidth: '1.75rem' }} aria-live="polite">{Number(linea.cantidad)}</span>
                                <Button variant="outline-secondary" size="sm" aria-label={'Agregar una unidad de '+linea.producto.nombre} onClick={() => onCantidad(linea.clave, String(Number(linea.cantidad)+1))}><Icon name="plus-lg" /></Button>
                            </div>}
                        <div className="d-flex gap-2">
                            <Button variant="outline-secondary" size="sm" aria-label={'Descuento a '+linea.producto.nombre} onClick={() => onDescuentoLinea(linea.clave)}><Icon name="percent" /></Button>
                            <Button variant="outline-danger" size="sm" aria-label={'Quitar '+linea.producto.nombre} onClick={() => onQuitar(linea.clave)}><Icon name="trash" /></Button>
                        </div>
                    </div>
                </li>;
            })}
        </ul>
        <div className="pos-carrito__pie">
            <div className="d-flex justify-content-between"><span>Subtotal</span><span>{formatearMonto(resumen.subtotal)}</span></div>
            {resumen.descuento > 0 && <div className="d-flex justify-content-between text-success"><span>Descuentos</span><span>-{formatearMonto(resumen.descuento)}</span></div>}
            <div className="d-flex justify-content-between fs-4 fw-semibold mt-1"><span>Total</span><span aria-live="polite">{formatearMonto(resumen.base)}</span></div>
            <div className="d-flex flex-wrap gap-2 mt-3">
                <Button variant="outline-secondary" size="sm" onClick={onDescuentoVenta}><Icon name="percent" className="me-1" />{descuentoVenta ? 'Cambiar descuento' : 'Descuento a la venta'}</Button>
                <Button variant="link" size="sm" className="text-danger ms-auto" onClick={onVaciar}>Vaciar</Button>
            </div>
            <Button size="lg" className="w-100 mt-3" disabled={resumen.base < 1} onClick={onCobrar} aria-keyshortcuts="F9">
                Cobrar {formatearMonto(resumen.base)}<kbd className="pos-carrito__atajo d-none d-lg-inline-block ms-2">F9</kbd>
            </Button>
        </div>
    </div>;
}
