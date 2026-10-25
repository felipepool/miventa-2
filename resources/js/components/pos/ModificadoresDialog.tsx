import { useState } from 'react';
import { Button, Form } from 'react-bootstrap';
import { FormDialog, Icon } from '@/components/ui';
import { formatearMonto } from '@/lib/caja';
import { resumenDeReglas } from '@/lib/modificadores';
import { cobrarGrupo } from '@/lib/venta';
import type { GrupoPos, ProductoPos, SeleccionModificador } from '@/types/venta';

interface ModificadoresDialogProps {
    producto: ProductoPos;
    onAgregar: (seleccion: SeleccionModificador[]) => void;
    onHide: () => void;
}

/** Texto de lo que falta para cumplir las reglas del grupo, o `null` si ya está completo. */
function faltante(grupo: GrupoPos, unidades: number): string | null {
    if (unidades < grupo.minimo) { return grupo.minimo === 1 ? 'Elige una opción.' : 'Elige al menos '+grupo.minimo+'.'; }

    return null;
}

/**
 * Elección de modificadores de un producto, con el precio actualizándose en vivo. Los grupos de
 * selección única usan un selector desplegable para no ocupar espacio cuando tienen muchas opciones.
 */
export default function ModificadoresDialog({ producto, onAgregar, onHide }: ModificadoresDialogProps) {
    const [elegidos, setElegidos] = useState<Record<string, SeleccionModificador[]>>({});
    const unidades = (grupo: GrupoPos) => (elegidos[grupo.id] ?? []).reduce((suma, elegido) => suma+elegido.cantidad, 0);
    const maximoDe = (grupo: GrupoPos) => (grupo.tipo_seleccion === 'unica' ? 1 : grupo.maximo);
    const adicional = producto.grupos_modificadores.reduce((suma, grupo) => suma+cobrarGrupo(grupo, elegidos[grupo.id] ?? []), 0);
    const incompleto = producto.grupos_modificadores.some((grupo) => faltante(grupo, unidades(grupo)) !== null);

    const cambiar = (grupo: GrupoPos, opcionId: string, cantidad: number) => setElegidos((actuales) => {
        const sinOpcion = (actuales[grupo.id] ?? []).filter((elegido) => elegido.id !== opcionId);

        return { ...actuales, [grupo.id]: cantidad > 0 ? [...sinOpcion, { id: opcionId, cantidad }] : sinOpcion };
    });

    const elegirUnica = (grupo: GrupoPos, opcionId: string) => setElegidos((actuales) => ({ ...actuales, [grupo.id]: opcionId === '' ? [] : [{ id: opcionId, cantidad: 1 }] }));

    return <FormDialog title={producto.nombre} size="md" onHide={onHide} actions={<>
        <Button variant="outline-secondary" onClick={onHide}>Cancelar</Button>
        <Button disabled={incompleto} onClick={() => onAgregar(Object.values(elegidos).flat())}>Agregar · {formatearMonto(producto.precio+adicional)}</Button>
    </>}>
        {producto.grupos_modificadores.map((grupo) => {
            const enGrupo = elegidos[grupo.id] ?? [];
            const total = unidades(grupo);
            const maximo = maximoDe(grupo);
            const pendiente = faltante(grupo, total);
            const idCampo = 'grupo-'+grupo.id;

            return <fieldset key={grupo.id} className="mb-4">
                <legend className="h6 mb-0" id={idCampo+'-titulo'}>{grupo.nombre}</legend>
                <p className={'small mb-2 '+(pendiente ? 'text-danger' : 'text-body-secondary')} role={pendiente ? 'alert' : undefined}>{pendiente ?? resumenDeReglas(grupo)}</p>
                {grupo.tipo_seleccion === 'unica' ? <Form.Select id={idCampo} aria-labelledby={idCampo+'-titulo'} value={enGrupo[0]?.id ?? ''} isInvalid={pendiente !== null} onChange={(event) => elegirUnica(grupo, event.target.value)}>
                    <option value="">{grupo.minimo === 0 ? 'Ninguno' : 'Selecciona una opción'}</option>
                    {grupo.modificadores.map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.nombre}{opcion.precio_adicional > 0 ? ' (+'+formatearMonto(opcion.precio_adicional)+')' : ''}</option>)}
                </Form.Select> : grupo.modificadores.map((opcion) => {
                    const cantidad = enGrupo.find((elegido) => elegido.id === opcion.id)?.cantidad ?? 0;
                    const precio = opcion.precio_adicional > 0 ? ' +'+formatearMonto(opcion.precio_adicional) : '';

                    if (grupo.tipo_seleccion === 'cantidad') {
                        const tope = Math.min(opcion.cantidad_maxima ?? Infinity, maximo === null ? Infinity : cantidad+(maximo-total));

                        return <div key={opcion.id} className="d-flex align-items-center justify-content-between gap-2 py-1">
                            <span>{opcion.nombre}<small className="text-body-secondary">{precio}</small></span>
                            <div className="d-flex align-items-center gap-2">
                                <Button variant="outline-secondary" size="sm" disabled={cantidad === 0} aria-label={'Quitar una unidad de '+opcion.nombre} onClick={() => cambiar(grupo, opcion.id, cantidad-1)}><Icon name="dash-lg" /></Button>
                                <span className="text-center" style={{ minWidth: '1.5rem' }} aria-live="polite">{cantidad}</span>
                                <Button variant="outline-secondary" size="sm" disabled={cantidad >= tope} aria-label={'Agregar una unidad de '+opcion.nombre} onClick={() => cambiar(grupo, opcion.id, cantidad+1)}><Icon name="plus-lg" /></Button>
                            </div>
                        </div>;
                    }

                    const alcanzoElMaximo = maximo !== null && total >= maximo && cantidad === 0;

                    return <Form.Check key={opcion.id} id={'mod-'+opcion.id} type="checkbox" name={idCampo}
                        label={<>{opcion.nombre}<small className="text-body-secondary">{precio}</small></>} checked={cantidad > 0} disabled={alcanzoElMaximo}
                        onChange={(event) => cambiar(grupo, opcion.id, event.target.checked ? 1 : 0)} className="py-1" />;
                })}
            </fieldset>;
        })}
    </FormDialog>;
}
