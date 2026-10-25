import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Col, Form, Row, Spinner } from 'react-bootstrap';
import { useLocation, useNavigate, useOutletContext } from 'react-router-dom';
import { FormDialog, Icon, TextField } from '@/components/ui';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import type { GrupoModificador, TipoSeleccion } from '@/types/catalogo';
import type { ModificadoresContexto } from './Index';

interface FilaModificador {
    clave: string;
    id?: string;
    nombre: string;
    precio: string;
    maxima: string;
    activo: boolean;
}

const AYUDA_TIPO: Record<TipoSeleccion, string> = {
    unica: 'El cliente elige una sola opción, por ejemplo el tipo de pan.',
    multiple: 'El cliente puede elegir varias opciones, cada una sí o no, por ejemplo los agregados.',
    cantidad: 'El cliente puede elegir varias unidades de cada opción, por ejemplo las salsas.',
};

let contadorFilas = 0;
const nuevaFila = (valores: Partial<FilaModificador> = {}): FilaModificador => ({ clave: 'fila-'+(contadorFilas++), nombre: '', precio: '0', maxima: '', activo: true, ...valores });
const numeroOCero = (valor: string) => (valor.trim() === '' ? 0 : Number(valor));
const numeroONulo = (valor: string) => (valor.trim() === '' ? null : Number(valor));

export default function GrupoFormulario({ grupo }: { grupo?: GrupoModificador }) {
    const { onGuardado } = useOutletContext<ModificadoresContexto>();
    const location = useLocation();
    const navigate = useNavigate();
    const formId = useId();
    const formulario = useRef<HTMLFormElement>(null);
    const [nombre, setNombre] = useState(grupo?.nombre ?? '');
    const [referencia, setReferencia] = useState(grupo?.referencia ?? '');
    const [tipo, setTipo] = useState<TipoSeleccion>(grupo?.tipo_seleccion ?? 'multiple');
    const [minimo, setMinimo] = useState(String(grupo?.minimo ?? 0));
    const [maximo, setMaximo] = useState(grupo?.maximo === null || grupo?.maximo === undefined ? '' : String(grupo.maximo));
    const [gratis, setGratis] = useState(grupo?.cantidad_gratis === null || grupo?.cantidad_gratis === undefined ? '' : String(grupo.cantidad_gratis));
    const [extra, setExtra] = useState(String(grupo?.precio_extra ?? 0));
    const [filas, setFilas] = useState<FilaModificador[]>(() => grupo
        ? grupo.modificadores.map((modificador) => nuevaFila({ id: modificador.id, nombre: modificador.nombre, precio: String(modificador.precio_adicional),
            maxima: modificador.cantidad_maxima === null ? '' : String(modificador.cantidad_maxima), activo: modificador.activo }))
        : [nuevaFila()]);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const cerrar = () => navigate('/modificadores'+location.search, { replace: true });
    const esUnica = tipo === 'unica';

    useEffect(() => {
        if (Object.keys(errores).length) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const actualizarFila = (clave: string, cambios: Partial<FilaModificador>) => setFilas((actuales) => actuales.map((fila) => (fila.clave === clave ? { ...fila, ...cambios } : fila)));
    const moverFila = (indice: number, desplazamiento: number) => setFilas((actuales) => {
        const destino = indice+desplazamiento;
        if (destino < 0 || destino >= actuales.length) { return actuales; }
        const copia = [...actuales];
        const [movida] = copia.splice(indice, 1);
        if (!movida) { return actuales; }
        copia.splice(destino, 0, movida);
        return copia;
    });

    const guardar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        const payload = {
            nombre, referencia: referencia || null, tipo_seleccion: tipo,
            minimo: numeroOCero(minimo), maximo: esUnica ? 1 : numeroONulo(maximo),
            cantidad_gratis: esUnica ? null : numeroONulo(gratis), precio_extra: esUnica ? 0 : numeroOCero(extra),
            modificadores: filas.map((fila) => ({ id: fila.id, nombre: fila.nombre, precio_adicional: numeroOCero(fila.precio),
                cantidad_maxima: tipo === 'cantidad' ? numeroONulo(fila.maxima) : null, activo: fila.activo })),
        };
        try {
            const respuesta = grupo ? await api.put<{ data: GrupoModificador }>('/grupos-modificadores/'+grupo.id, payload) : await api.post<{ data: GrupoModificador }>('/grupos-modificadores', payload);
            onGuardado(respuesta.data.data, grupo ? 'actualizado' : 'creado');
            cerrar();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(Object.keys(validacion).length ? '' : mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return <FormDialog title={grupo ? 'Editar grupo' : 'Nuevo grupo'} size="lg" onHide={cerrar} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={cerrar}>Cancelar</Button>
        <Button type="submit" form={formId} disabled={procesando}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Guardando…' : grupo ? 'Guardar cambios' : 'Crear grupo'}
        </Button>
    </>}>
        <form ref={formulario} id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void guardar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Datos del grupo de modificadores</legend>
                <Row>
                    <Col md={7}><TextField label="Nombre del grupo" value={nombre} maxLength={100} required error={errores.nombre} onChange={(event) => setNombre(event.target.value)} /></Col>
                    <Col md={5}><TextField label="Referencia (opcional)" value={referencia} maxLength={100} error={errores.referencia} onChange={(event) => setReferencia(event.target.value)} /></Col>
                </Row>
                <Form.Group controlId="grupo-tipo" className="mb-3"><Form.Label>Tipo de selección</Form.Label>
                    <Form.Select value={tipo} isInvalid={Boolean(errores.tipo_seleccion)} onChange={(event) => setTipo(event.target.value as TipoSeleccion)}>
                        <option value="unica">Selección única</option><option value="multiple">Selección múltiple</option><option value="cantidad">Por cantidad</option>
                    </Form.Select>
                    {errores.tipo_seleccion ? <Form.Control.Feedback type="invalid">{errores.tipo_seleccion}</Form.Control.Feedback> : <Form.Text muted>{AYUDA_TIPO[tipo]}</Form.Text>}
                </Form.Group>
                {esUnica
                    ? <Form.Check id="grupo-obligatorio" type="switch" className="mb-3" label="Es obligatorio elegir una opción" checked={minimo === '1'} onChange={(event) => setMinimo(event.target.checked ? '1' : '0')} />
                    : <>
                        <Row>
                            <Col sm={6}><TextField label="Mínimo a elegir" type="number" min="0" step="1" inputMode="numeric" value={minimo} error={errores.minimo} onChange={(event) => setMinimo(event.target.value)} /></Col>
                            <Col sm={6}><TextField label="Máximo a elegir (opcional)" type="number" min="1" step="1" inputMode="numeric" value={maximo} error={errores.maximo} hint="Vacío significa sin límite." onChange={(event) => setMaximo(event.target.value)} /></Col>
                        </Row>
                        <Row>
                            <Col sm={6}><TextField label="Unidades gratis (opcional)" type="number" min="0" step="1" inputMode="numeric" value={gratis} error={errores.cantidad_gratis} onChange={(event) => setGratis(event.target.value)} /></Col>
                            <Col sm={6}><TextField label="Precio extra por unidad (CLP, IVA incluido)" type="number" min="0" step="1" inputMode="numeric" value={extra} error={errores.precio_extra}
                                hint="Se cobra por cada unidad que supere las gratis." onChange={(event) => setExtra(event.target.value)} /></Col>
                        </Row>
                    </>}
                <h3 className="h6 mt-2">Modificadores</h3>
                <p className="small text-body-secondary">Cada modificador puede ser sin costo adicional (precio 0) o tener un costo, en pesos con IVA incluido.</p>
                {errores.modificadores && <p className="text-danger small" role="alert">{errores.modificadores}</p>}
                {filas.map((fila, indice) => <div key={fila.clave} className="border rounded p-3 mb-3" role="group" aria-label={'Modificador '+(indice+1)}>
                    <Row className="g-2 align-items-start">
                        <Col md={tipo === 'cantidad' ? 5 : 7}><TextField label="Nombre" value={fila.nombre} maxLength={100} required className="mb-0" error={errores['modificadores.'+indice+'.nombre']}
                            onChange={(event) => actualizarFila(fila.clave, { nombre: event.target.value })} /></Col>
                        <Col xs={6} md={tipo === 'cantidad' ? 4 : 5}><TextField label="Precio adicional (CLP)" type="number" min="0" step="1" inputMode="numeric" value={fila.precio} className="mb-0"
                            error={errores['modificadores.'+indice+'.precio_adicional']} hint="0 = sin costo" onChange={(event) => actualizarFila(fila.clave, { precio: event.target.value })} /></Col>
                        {tipo === 'cantidad' && <Col xs={6} md={3}><TextField label="Máx. por pedido" type="number" min="1" step="1" inputMode="numeric" value={fila.maxima} className="mb-0"
                            error={errores['modificadores.'+indice+'.cantidad_maxima']} onChange={(event) => actualizarFila(fila.clave, { maxima: event.target.value })} /></Col>}
                    </Row>
                    <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                        <Form.Check id={'modificador-activo-'+fila.clave} type="switch" label="Activo" checked={fila.activo} onChange={(event) => actualizarFila(fila.clave, { activo: event.target.checked })} />
                        <div className="d-flex gap-2">
                            <Button variant="outline-secondary" size="sm" disabled={indice === 0} aria-label={'Subir '+(fila.nombre || 'modificador '+(indice+1))} onClick={() => moverFila(indice, -1)}><Icon name="arrow-up" /></Button>
                            <Button variant="outline-secondary" size="sm" disabled={indice === filas.length-1} aria-label={'Bajar '+(fila.nombre || 'modificador '+(indice+1))} onClick={() => moverFila(indice, 1)}><Icon name="arrow-down" /></Button>
                            {!fila.id && <Button variant="outline-danger" size="sm" aria-label={'Quitar '+(fila.nombre || 'modificador '+(indice+1))} onClick={() => setFilas((actuales) => actuales.filter((actual) => actual.clave !== fila.clave))}><Icon name="trash" /></Button>}
                        </div>
                    </div>
                </div>)}
                <Button variant="outline-primary" onClick={() => setFilas((actuales) => [...actuales, nuevaFila()])}><Icon name="plus-lg" className="me-2" />Agregar modificador</Button>
                {errorGeneral && <p className="text-danger mt-3" role="alert">{errorGeneral}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}
