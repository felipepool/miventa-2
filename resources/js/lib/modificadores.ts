import type { GrupoModificador, TipoSeleccion } from '@/types/catalogo';

export const ETIQUETAS_SELECCION: Record<TipoSeleccion, string> = { unica: 'Selección única', multiple: 'Selección múltiple', cantidad: 'Por cantidad' };

/** Reglas del grupo en lenguaje simple (ej. «Elige 1 · obligatorio»). */
export function resumenDeReglas(grupo: Pick<GrupoModificador, 'tipo_seleccion' | 'minimo' | 'maximo' | 'cantidad_gratis' | 'precio_extra'>): string {
    const partes = [grupo.tipo_seleccion === 'unica' ? 'Elige 1' : grupo.maximo === null ? 'Sin límite' : 'Hasta '+grupo.maximo];
    if (grupo.minimo > 0) { partes.push(grupo.tipo_seleccion === 'unica' || grupo.minimo === 1 ? 'obligatorio' : 'mínimo '+grupo.minimo); }
    if (grupo.cantidad_gratis !== null && grupo.tipo_seleccion !== 'unica') {
        partes.push(grupo.cantidad_gratis+' gratis, luego +$'+grupo.precio_extra.toLocaleString('es-CL')+' c/u');
    }
    return partes.join(' · ');
}

