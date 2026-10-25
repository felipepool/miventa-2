import type { Descuento, GrupoPos, LineaCarrito, SeleccionModificador } from '@/types/venta';

/**
 * Cálculo de la venta en pesos enteros, solo para mostrar el total en la pantalla.
 * El servidor lo vuelve a calcular con las mismas reglas y es quien manda.
 */

/** Redondeo del efectivo (Ley 20.956): al múltiplo de $10; terminar en 1 a 5 baja y de 6 a 9 sube. */
export function redondearEfectivo(monto: number): number {
    const ultimo = monto % 10;

    return ultimo <= 5 ? monto-ultimo : monto+(10-ultimo);
}

export function montoDescuento(base: number, descuento: Descuento): number {
    return descuento.tipo === 'porcentaje' ? Math.floor((base*descuento.valor+50)/100) : descuento.valor;
}

/** Reparte un descuento entre las líneas en proporción a su valor, sin dejar pesos sueltos. */
export function repartirDescuento(descuento: number, bases: number[]): number[] {
    const total = bases.reduce((suma, base) => suma+base, 0);

    if (descuento <= 0 || total <= 0) { return bases.map(() => 0); }

    const partes = bases.map((base) => Math.floor((descuento*base)/total));
    const restos = bases.map((base) => (descuento*base)%total);
    const sobrante = descuento-partes.reduce((suma, parte) => suma+parte, 0);
    const orden = bases.map((_, indice) => indice).sort((a, b) => (restos[b] ?? 0)-(restos[a] ?? 0) || a-b);

    orden.slice(0, sobrante).forEach((indice) => { partes[indice] = (partes[indice] ?? 0)+1; });

    return partes;
}

/** Lo que cobran las opciones elegidas de un grupo, en el orden en que se agregaron. */
export function cobrarGrupo(grupo: GrupoPos, seleccion: SeleccionModificador[]): number {
    let contadas = 0;
    let total = 0;

    for (const elegido of seleccion) {
        const opcion = grupo.modificadores.find((candidata) => candidata.id === elegido.id);

        if (!opcion) { continue; }

        const conRecargo = grupo.cantidad_gratis === null ? 0 : Math.max(0, Math.min(elegido.cantidad, contadas+elegido.cantidad-grupo.cantidad_gratis));
        total += elegido.cantidad*opcion.precio_adicional+conRecargo*grupo.precio_extra;
        contadas += elegido.cantidad;
    }

    return total;
}

/** Cuánto suman los modificadores de una unidad del producto. */
export function modificadoresPorUnidad(linea: LineaCarrito): number {
    return linea.producto.grupos_modificadores.reduce((suma, grupo) => {
        const delGrupo = linea.modificadores.filter((elegido) => grupo.modificadores.some((opcion) => opcion.id === elegido.id));

        return suma+cobrarGrupo(grupo, delGrupo);
    }, 0);
}

/** Subtotal de la línea: cantidad × (precio + modificadores), redondeado al peso. */
export function subtotalLinea(linea: LineaCarrito): number {
    const milesimas = Math.round(Number(linea.cantidad)*1000);

    return Math.floor((milesimas*(linea.producto.precio+modificadoresPorUnidad(linea))+500)/1000);
}

export interface ResumenCarrito {
    subtotal: number;
    descuento: number;
    base: number;
    lineas: { subtotal: number; descuento: number; total: number }[];
}

export function resumirCarrito(lineas: LineaCarrito[], descuentoVenta: Descuento | null): ResumenCarrito {
    const subtotales = lineas.map(subtotalLinea);
    const descuentosLinea = lineas.map((linea, indice) => (linea.descuento ? Math.min(montoDescuento(subtotales[indice] ?? 0, linea.descuento), subtotales[indice] ?? 0) : 0));
    const bases = subtotales.map((subtotal, indice) => subtotal-(descuentosLinea[indice] ?? 0));
    const baseTotal = bases.reduce((suma, base) => suma+base, 0);
    const deVenta = descuentoVenta ? Math.min(montoDescuento(baseTotal, descuentoVenta), baseTotal) : 0;
    const repartido = repartirDescuento(deVenta, bases);
    const resumenLineas = subtotales.map((subtotal, indice) => {
        const descuento = (descuentosLinea[indice] ?? 0)+(repartido[indice] ?? 0);

        return { subtotal, descuento, total: subtotal-descuento };
    });
    const subtotal = subtotales.reduce((suma, valor) => suma+valor, 0);
    const descuento = resumenLineas.reduce((suma, linea) => suma+linea.descuento, 0);

    return { subtotal, descuento, base: subtotal-descuento, lineas: resumenLineas };
}

export interface ResumenCobro {
    sinEfectivo: number;
    porCobrar: number;
    enEfectivo: number;
    redondeo: number;
    total: number;
}

/** Lo que cobra el efectivo después de los demás medios, con su redondeo. */
export function resumirCobro(base: number, montosSinEfectivo: number[], usaEfectivo: boolean): ResumenCobro {
    const sinEfectivo = montosSinEfectivo.reduce((suma, monto) => suma+monto, 0);
    const porCobrar = Math.max(0, base-sinEfectivo);
    const enEfectivo = usaEfectivo ? redondearEfectivo(porCobrar) : 0;
    const redondeo = usaEfectivo ? enEfectivo-porCobrar : 0;

    return { sinEfectivo, porCobrar, enEfectivo, redondeo, total: usaEfectivo ? sinEfectivo+enEfectivo : sinEfectivo };
}

/** Clave aleatoria para que reenviar una venta (doble clic, red lenta) no la duplique. */
export function nuevaClaveDeVenta(): string {
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}
