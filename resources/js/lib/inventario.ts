import type { UnidadVenta } from '@/types/catalogo';

const ABREVIATURAS: Record<UnidadVenta, string> = { unidad: 'u', kg: 'kg', g: 'g', l: 'l', ml: 'ml', m: 'm' };

/** Nombre de la unidad para pedir una cantidad (ej. «kg» o «litros»). */
export const ETIQUETAS_UNIDAD: Record<UnidadVenta, string> = { unidad: 'unidades', kg: 'kg', g: 'g', l: 'litros', ml: 'ml', m: 'metros' };

/** Muestra una cantidad decimal sin ceros sobrantes y con su unidad (ej. "2,5 kg"). */
export function formatearCantidad(cantidad: string, unidad: UnidadVenta): string {
    return Number(cantidad).toLocaleString('es-CL', { maximumFractionDigits: 3 })+' '+ABREVIATURAS[unidad];
}

/** Los productos por unidad se mueven en cantidades enteras; el resto admite hasta tres decimales. */
export function pasoDeCantidad(unidad: UnidadVenta): string {
    return unidad === 'unidad' ? '1' : '0.001';
}

