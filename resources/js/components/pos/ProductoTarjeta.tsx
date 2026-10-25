import { Icon } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import type { ProductoPos } from '@/types/venta';

/** Cantidad de tonos definidos en `.pos-producto__inicial--N`. */
const TONOS = 6;

/** Tono estable por categoría para reconocer los productos de un vistazo. */
const tonoDe = (clave: string) => [...clave].reduce((suma, letra) => suma+letra.charCodeAt(0), 0) % TONOS;

/** Primeras letras o dígitos de las dos primeras palabras, ignorando signos como paréntesis. */
const inicialesDe = (nombre: string) => (nombre.match(/[\p{L}\p{N}]+/gu) ?? []).slice(0, 2).map((palabra) => palabra[0]).join('').toUpperCase();

interface Props {
    producto: ProductoPos;
    /** Cantidad ya agregada al carrito, en la unidad de venta del producto. */
    enCarrito: number;
    onElegir: () => void;
}

export default function ProductoTarjeta({ producto, enCarrito, onElegir }: Props) {
    const conModificadores = producto.grupos_modificadores.length > 0;
    const etiqueta = [producto.nombre, formatearMonto(producto.precio), enCarrito > 0 ? enCarrito+' en el carrito' : null].filter(Boolean).join(', ');

    return <button type="button" className={'pos-producto'+(enCarrito > 0 ? ' is-en-carrito' : '')} onClick={onElegir} aria-label={etiqueta}>
        <span className={'pos-producto__inicial pos-producto__inicial--'+tonoDe(producto.categoria?.id ?? producto.nombre)} aria-hidden="true">{inicialesDe(producto.nombre)}</span>
        {enCarrito > 0 && <span className="pos-producto__cantidad" aria-hidden="true">{enCarrito}</span>}
        <span className="pos-producto__cuerpo">
            {producto.categoria && <span className="pos-producto__categoria">{producto.categoria.nombre}</span>}
            <span className="pos-producto__nombre">{producto.nombre}</span>
        </span>
        <span className="pos-producto__pie">
            <span className="pos-producto__precio">{formatearMonto(producto.precio)}{producto.unidad_venta !== 'unidad' && <small> /{producto.unidad_venta}</small>}</span>
            {conModificadores && <span className="pos-producto__mods" title="Tiene opciones"><Icon name="sliders" /></span>}
        </span>
    </button>;
}
