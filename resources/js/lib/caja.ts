/** Monto en pesos chilenos, con separador de miles (ej. «$20.000»). */
export function formatearMonto(monto: number): string {
    return (monto < 0 ? '-$' : '$')+Math.abs(monto).toLocaleString('es-CL');
}

/** Fecha y hora cortas en la zona del navegador. */
export function formatearFechaHora(valor: string): string {
    return new Date(valor).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' });
}

/** Texto de la diferencia de un cierre: sobrante, faltante o exacto. */
export function describirDiferencia(diferencia: number): string {
    if (diferencia === 0) { return 'Cuadra exacto'; }

    return diferencia > 0 ? 'Sobran '+formatearMonto(diferencia) : 'Faltan '+formatearMonto(-diferencia);
}
