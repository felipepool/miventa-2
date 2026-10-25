import { useEffect, useMemo, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Alert, Badge, Button, Card, Form, Spinner } from 'react-bootstrap';
import { Link } from 'react-router-dom';
import CantidadDialog from '@/components/pos/CantidadDialog';
import CarritoPanel from '@/components/pos/CarritoPanel';
import CobroDialog from '@/components/pos/CobroDialog';
import DescuentoDialog from '@/components/pos/DescuentoDialog';
import ModificadoresDialog from '@/components/pos/ModificadoresDialog';
import ProductoTarjeta from '@/components/pos/ProductoTarjeta';
import { FormDialog, Icon } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import { mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { useTitulo } from '@/lib/useTitulo';
import { resumirCarrito } from '@/lib/venta';
import type { CatalogoPos, Descuento, LineaCarrito, ProductoPos, SeleccionModificador, Venta } from '@/types/venta';

type Dialogo =
    | { tipo: 'modificadores'; producto: ProductoPos }
    | { tipo: 'cantidad'; producto: ProductoPos }
    | { tipo: 'descuento-linea'; clave: string }
    | { tipo: 'descuento-venta' }
    | { tipo: 'carrito' }
    | { tipo: 'cobro' };

let contadorLineas = 0;
const nuevaClave = () => 'linea-'+(contadorLineas++);

/** Suma dos cantidades decimales sin arrastrar errores de punto flotante. */
const sumarCantidades = (a: string, b: string) => String(Math.round((Number(a)+Number(b))*1000)/1000);

export default function PosIndex() {
    useTitulo('Vender');
    const consulta = useConsultaApi<{ data: CatalogoPos }>('/pos');
    const catalogo = consulta.datos?.data;
    const [lineas, setLineas] = useState<LineaCarrito[]>([]);
    const [descuentoVenta, setDescuentoVenta] = useState<Descuento | null>(null);
    const [motivo, setMotivo] = useState('');
    const [buscar, setBuscar] = useState('');
    const [categoria, setCategoria] = useState('');
    const [dialogo, setDialogo] = useState<Dialogo | null>(null);
    const [vendida, setVendida] = useState<Venta | null>(null);
    const resumen = useMemo(() => resumirCarrito(lineas, descuentoVenta), [lineas, descuentoVenta]);
    const enCarrito = useMemo(() => lineas.reduce((cantidades, linea) => cantidades.set(linea.producto.id, Number(sumarCantidades(String(cantidades.get(linea.producto.id) ?? 0), linea.cantidad))), new Map<string, number>()), [lineas]);
    const hayDescuentos = descuentoVenta !== null || lineas.some((linea) => linea.descuento !== null);

    const productos = useMemo(() => {
        const texto = buscar.trim().toLowerCase();

        return (catalogo?.productos ?? []).filter((producto) => (categoria === '' || producto.categoria?.id === categoria)
            && (texto === '' || [producto.nombre, producto.sku, producto.codigo_barras].some((campo) => campo?.toLowerCase().includes(texto))));
    }, [catalogo, buscar, categoria]);

    const agregar = (producto: ProductoPos, cantidad: string, modificadores: SeleccionModificador[]) => setLineas((actuales) => {
        const igual = modificadores.length === 0 ? actuales.find((linea) => linea.producto.id === producto.id && linea.modificadores.length === 0 && linea.descuento === null) : undefined;

        return igual ? actuales.map((linea) => (linea === igual ? { ...linea, cantidad: sumarCantidades(linea.cantidad, cantidad) } : linea))
            : [...actuales, { clave: nuevaClave(), producto, cantidad, modificadores, descuento: null }];
    });

    const elegirProducto = (producto: ProductoPos) => {
        if (producto.grupos_modificadores.length > 0) { setDialogo({ tipo: 'modificadores', producto }); } else if (producto.unidad_venta !== 'unidad') { setDialogo({ tipo: 'cantidad', producto }); } else { agregar(producto, '1', []); }
    };

    const alEscribir = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key !== 'Enter') { return; }
        event.preventDefault();
        const texto = buscar.trim().toLowerCase();
        const exacto = (catalogo?.productos ?? []).find((producto) => texto !== '' && (producto.codigo_barras?.toLowerCase() === texto || producto.sku?.toLowerCase() === texto));
        const elegido = exacto ?? (productos.length === 1 ? productos[0] : undefined);

        if (elegido) { elegirProducto(elegido); setBuscar(''); } else if (texto !== '') { notificar('No hay un producto con ese código o nombre.', 'error'); }
    };

    const cambiarCantidad = (clave: string, cantidad: string) => setLineas((actuales) => actuales.flatMap((linea) => {
        if (linea.clave !== clave) { return [linea]; }

        return Number(cantidad) <= 0 && linea.producto.unidad_venta === 'unidad' ? [] : [{ ...linea, cantidad }];
    }));

    const reiniciar = () => { setLineas([]); setDescuentoVenta(null); setMotivo(''); setDialogo(null); };

    const puedeCobrar = lineas.length > 0 && resumen.base >= 1 && dialogo === null && vendida === null;

    /** En escritorio, F9 abre el cobro, igual que el botón Cobrar del carrito. */
    useEffect(() => {
        if (!puedeCobrar) { return undefined; }

        const cobrarConF9 = (event: globalThis.KeyboardEvent) => {
            if (event.key !== 'F9' || !window.matchMedia('(min-width: 992px)').matches) { return; }
            event.preventDefault();
            setDialogo({ tipo: 'cobro' });
        };

        window.addEventListener('keydown', cobrarConF9);

        return () => window.removeEventListener('keydown', cobrarConF9);
    }, [puedeCobrar]);

    const construirVenta = (pagos: Record<string, unknown>[], clave: string) => ({
        items: lineas.map((linea) => ({ producto: linea.producto.id, cantidad: linea.cantidad, modificadores: linea.modificadores, descuento: linea.descuento })),
        descuento: descuentoVenta, motivo_descuento: hayDescuentos ? motivo : null, pagos, clave_idempotencia: clave,
    });

    if (consulta.error) {
        return <div role="alert"><p>No se pudo cargar el punto de venta. {mensajeDeError(consulta.error)}</p><Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button></div>;
    }

    if (!catalogo) {
        return <p role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando productos…</p>;
    }

    if (!catalogo.turno) {
        return <Card className="mx-auto" style={{ maxWidth: '32rem' }}><Card.Body className="text-center py-5">
            <Icon name="cash-stack" className="fs-1 text-body-secondary d-block mb-3" />
            <h1 className="h5">Abre tu caja para vender</h1>
            <p className="text-body-secondary">Las ventas se registran en tu turno de caja.</p>
            <Link to="/caja" className="btn btn-primary">Ir a Mi caja</Link>
        </Card.Body></Card>;
    }

    const lineaDelDialogo = dialogo?.tipo === 'descuento-linea' ? lineas.find((linea) => linea.clave === dialogo.clave) : undefined;
    const panel = <CarritoPanel lineas={lineas} resumen={resumen} descuentoVenta={descuentoVenta} onCantidad={cambiarCantidad}
        onQuitar={(clave) => setLineas((actuales) => actuales.filter((linea) => linea.clave !== clave))} onDescuentoLinea={(clave) => setDialogo({ tipo: 'descuento-linea', clave })}
        onDescuentoVenta={() => setDialogo({ tipo: 'descuento-venta' })} onVaciar={reiniciar} onCobrar={() => setDialogo({ tipo: 'cobro' })} />;
    const unidadesEnCarrito = lineas.length;
    const tope = catalogo.descuento_sin_limite ? null : catalogo.descuento_maximo;

    return <>
        <div className="pos-pantalla">
            <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h1 className="h4 mb-0">Vender</h1>
                <Badge bg="light" text="dark" className="fs-6 fw-normal border">{catalogo.turno.caja} · Turno {catalogo.turno.numero}</Badge>
            </div>
            <div className="row gx-3 pos-pantalla__cuerpo">
                <div className="col-lg-8 pos-pantalla__catalogo">
                    <div className="input-group mb-3">
                        <span className="input-group-text" aria-hidden="true"><Icon name="search" /></span>
                        <Form.Control type="search" size="lg" autoFocus value={buscar} placeholder="Buscar o escanear código de barras" aria-label="Buscar producto por nombre, SKU o código de barras"
                            onChange={(event) => setBuscar(event.target.value)} onKeyDown={alEscribir} />
                    </div>
                    {catalogo.categorias.length > 0 && <div className="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Filtrar por categoría">
                        <Button size="sm" variant={categoria === '' ? 'primary' : 'outline-secondary'} aria-pressed={categoria === ''} onClick={() => setCategoria('')}>Todas</Button>
                        {catalogo.categorias.map((opcion) => <Button key={opcion.id} size="sm" variant={categoria === opcion.id ? 'primary' : 'outline-secondary'} aria-pressed={categoria === opcion.id} onClick={() => setCategoria(opcion.id)}>{opcion.nombre}</Button>)}
                    </div>}
                    {productos.length === 0
                        ? <p className="text-body-secondary" role="status">{catalogo.productos.length === 0 ? 'No hay productos a la venta. Crea productos con precio en Catálogo.' : 'No encontramos productos con esa búsqueda.'}</p>
                        : <div className="pos-grilla">{productos.map((producto) => <ProductoTarjeta key={producto.id} producto={producto} enCarrito={enCarrito.get(producto.id) ?? 0} onElegir={() => elegirProducto(producto)} />)}</div>}
                </div>
                <div className="col-lg-4 d-none d-lg-block"><div className="pos__carrito">{panel}</div></div>
            </div>
        </div>
        {lineas.length > 0 && <div className="d-lg-none position-sticky mt-3" style={{ bottom: '4.75rem', zIndex: 5 }}>
            <Button size="lg" className="w-100 shadow d-flex justify-content-between align-items-center" onClick={() => setDialogo({ tipo: 'carrito' })}>
                <span><Icon name="cart" className="me-2" />Ver carrito ({unidadesEnCarrito})</span><strong>{formatearMonto(resumen.base)}</strong>
            </Button>
        </div>}

        {dialogo?.tipo === 'modificadores' && <ModificadoresDialog producto={dialogo.producto} onHide={() => setDialogo(null)} onAgregar={(seleccion) => { agregar(dialogo.producto, '1', seleccion); setDialogo(null); }} />}
        {dialogo?.tipo === 'cantidad' && <CantidadDialog producto={dialogo.producto} onHide={() => setDialogo(null)} onAgregar={(cantidad) => { agregar(dialogo.producto, cantidad, []); setDialogo(null); }} />}
        {dialogo?.tipo === 'descuento-linea' && lineaDelDialogo && <DescuentoDialog titulo={'Descuento a '+lineaDelDialogo.producto.nombre} actual={lineaDelDialogo.descuento} motivoActual={motivo} tope={tope}
            onHide={() => setDialogo(null)} onGuardar={(descuento, nuevoMotivo) => { setLineas((actuales) => actuales.map((linea) => (linea.clave === lineaDelDialogo.clave ? { ...linea, descuento } : linea))); if (descuento) { setMotivo(nuevoMotivo); } setDialogo(null); }} />}
        {dialogo?.tipo === 'descuento-venta' && <DescuentoDialog titulo="Descuento a la venta" actual={descuentoVenta} motivoActual={motivo} tope={tope} onHide={() => setDialogo(null)}
            onGuardar={(descuento, nuevoMotivo) => { setDescuentoVenta(descuento); if (descuento) { setMotivo(nuevoMotivo); } setDialogo(null); }} />}
        {dialogo?.tipo === 'carrito' && <FormDialog title="Carrito" size="md" onHide={() => setDialogo(null)} actions={<Button variant="outline-secondary" onClick={() => setDialogo(null)}>Seguir vendiendo</Button>}>{panel}</FormDialog>}
        {dialogo?.tipo === 'cobro' && <CobroDialog base={resumen.base} medios={catalogo.medios_pago} construirVenta={construirVenta} onHide={() => setDialogo(null)}
            onVendida={(venta) => { reiniciar(); setVendida(venta); }} />}
        {vendida && <VentaRegistrada venta={vendida} onNueva={() => setVendida(null)} />}
    </>;
}

function VentaRegistrada({ venta, onNueva }: { venta: Venta; onNueva: () => void }) {
    const vuelto = (venta.pagos ?? []).reduce((suma, pago) => suma+pago.vuelto, 0);

    return <FormDialog title="Venta registrada" size="md" onHide={onNueva} actions={<>
        <Link to={'/ventas/'+venta.id} className="btn btn-outline-secondary d-inline-flex align-items-center">Ver detalle</Link>
        <Button autoFocus onClick={onNueva}>Nueva venta</Button>
    </>}>
        <div className="text-center" role="status">
            <Icon name="check-circle" className="fs-1 text-success d-block mb-2" />
            <p className="mb-1">Venta N° <strong>{venta.folio}</strong></p>
            <p className="fs-2 fw-semibold mb-1">{formatearMonto(venta.total)}</p>
            {venta.redondeo !== 0 && <p className="small text-body-secondary mb-1">Incluye redondeo de {formatearMonto(venta.redondeo)}</p>}
            {vuelto > 0 && <Alert variant="success" className="mt-3 mb-0 fs-5">Vuelto: <strong>{formatearMonto(vuelto)}</strong></Alert>}
        </div>
    </FormDialog>;
}

