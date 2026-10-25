import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Card, Form, Spinner } from 'react-bootstrap';
import { Link } from 'react-router-dom';
import CierreTurno from '@/components/caja/CierreTurno';
import ResumenTurno from '@/components/caja/ResumenTurno';
import { FormDialog, Icon, PageHeader, TextField } from '@/components/ui';
import { formatearFechaHora } from '@/lib/caja';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { useTitulo } from '@/lib/useTitulo';
import type { MiCaja, MovimientoCaja, TurnoCaja } from '@/types/turno';

const CLAVE_CAJA_RECORDADA = 'miventa.caja';

function leerCajaRecordada(): string | null {
    try {
        return window.localStorage.getItem(CLAVE_CAJA_RECORDADA);
    } catch {
        return null;
    }
}

function recordarCaja(id: string): void {
    try {
        window.localStorage.setItem(CLAVE_CAJA_RECORDADA, id);
    } catch {
        // Sin almacenamiento disponible: simplemente no se recuerda la caja.
    }
}

export default function CajaIndex() {
    useTitulo('Mi caja');
    const consulta = useConsultaApi<{ data: MiCaja }>('/caja');
    const [cerrado, setCerrado] = useState<TurnoCaja | null>(null);

    let contenido;
    if (consulta.error) {
        contenido = <div role="alert"><p>No se pudo cargar tu caja. {mensajeDeError(consulta.error)}</p><Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button></div>;
    } else if (!consulta.datos) {
        contenido = <p role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando tu caja…</p>;
    } else if (cerrado) {
        contenido = <TurnoCerrado turno={cerrado} onNueva={() => { setCerrado(null); consulta.recargar(); }} />;
    } else if (consulta.datos.data.turno) {
        contenido = <TurnoAbierto caja={consulta.datos.data} turno={consulta.datos.data.turno} onCambio={consulta.recargar} onCerrado={setCerrado} />;
    } else {
        contenido = <AbrirCaja caja={consulta.datos.data} onAbierta={() => { notificar('Caja abierta correctamente.'); consulta.recargar(); }} />;
    }

    return <>
        <PageHeader title="Mi caja" subtitle="Abre tu turno, registra el efectivo que entra y sale, y ciérralo contando la caja." />
        {contenido}
    </>;
}

function AbrirCaja({ caja, onAbierta }: { caja: MiCaja; onAbierta: () => void }) {
    const libres = caja.cajas.filter((opcion) => opcion.ocupada_por === null);
    const recordada = leerCajaRecordada();
    const inicial = libres.find((opcion) => opcion.id === recordada) ?? libres[0];
    const [elegida, setElegida] = useState(inicial?.id ?? '');
    const [monto, setMonto] = useState(inicial?.monto_sugerido === null || inicial?.monto_sugerido === undefined ? '' : String(inicial.monto_sugerido));
    const [nota, setNota] = useState('');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);

    const abrir = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            await api.post('/caja/abrir', { caja: elegida || null, monto_inicial: monto.trim() === '' ? null : Number(monto), nota: nota || null });
            recordarCaja(elegida);
            onAbierta();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(Object.keys(validacion).length ? '' : mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    if (caja.cajas.length === 0) {
        return <Card><Card.Body role="status">Esta sucursal no tiene cajas activas. Pide a un administrador que active o cree una.</Card.Body></Card>;
    }

    return <Card className="mx-auto" style={{ maxWidth: '32rem' }}>
        <Card.Body>
            <h2 className="h5">Abrir caja</h2>
            <form noValidate aria-busy={procesando} onSubmit={(event) => void abrir(event)}>
                <fieldset disabled={procesando}>
                    <legend className="visually-hidden">Datos de apertura</legend>
                    <Form.Group controlId="abrir-caja" className="mb-3"><Form.Label>Caja</Form.Label>
                        <Form.Select value={elegida} isInvalid={Boolean(errores.caja)} onChange={(event) => {
                            setElegida(event.target.value);
                            const opcion = caja.cajas.find((candidata) => candidata.id === event.target.value);
                            if (opcion?.monto_sugerido !== null && opcion?.monto_sugerido !== undefined) { setMonto(String(opcion.monto_sugerido)); }
                        }}>
                            <option value="">Elige una caja</option>
                            {caja.cajas.map((opcion) => <option key={opcion.id} value={opcion.id} disabled={opcion.ocupada_por !== null}>{opcion.nombre}{opcion.ocupada_por ? ' (en uso por '+opcion.ocupada_por+')' : ''}</option>)}
                        </Form.Select>
                        {errores.caja && <Form.Control.Feedback type="invalid">{errores.caja}</Form.Control.Feedback>}
                    </Form.Group>
                    <TextField label="Monto inicial (CLP)" type="number" min="0" step="1" inputMode="numeric" value={monto} required error={errores.monto_inicial}
                        hint="El efectivo con que empieza la caja." onChange={(event) => setMonto(event.target.value)} />
                    <TextField label="Nota (opcional)" value={nota} maxLength={255} error={errores.nota} onChange={(event) => setNota(event.target.value)} />
                    {errorGeneral && <p className="text-danger" role="alert">{errorGeneral}</p>}
                    {libres.length === 0 && <p className="small text-body-secondary">Todas las cajas están en uso. Pide a quien la usa que cierre su turno.</p>}
                    <Button type="submit" disabled={procesando || libres.length === 0}>
                        {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
                        {procesando ? 'Abriendo…' : 'Abrir caja'}
                    </Button>
                </fieldset>
            </form>
        </Card.Body>
    </Card>;
}

interface TurnoAbiertoProps {
    caja: MiCaja;
    turno: TurnoCaja;
    onCambio: () => void;
    onCerrado: (turno: TurnoCaja) => void;
}

function TurnoAbierto({ caja, turno, onCambio, onCerrado }: TurnoAbiertoProps) {
    const [movimiento, setMovimiento] = useState<'ingreso' | 'retiro' | null>(null);
    const [cerrando, setCerrando] = useState(false);

    return <>
        <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h2 className="h5 mb-0">{turno.caja?.nombre} · Turno {turno.numero}</h2>
                <small className="text-body-secondary">Abierto desde {formatearFechaHora(turno.abierta_at)}</small>
            </div>
            <div className="d-flex flex-wrap gap-2">
                <Button variant="outline-success" onClick={() => setMovimiento('ingreso')}><Icon name="plus-circle" className="me-2" />Registrar ingreso</Button>
                <Button variant="outline-danger" onClick={() => setMovimiento('retiro')}><Icon name="dash-circle" className="me-2" />Registrar retiro</Button>
                <Button variant="danger" onClick={() => setCerrando(true)}><Icon name="lock" className="me-2" />Cerrar caja</Button>
            </div>
        </div>
        {!caja.puede_ver_esperado && <p className="small text-body-secondary">Cierre ciego: cuenta el efectivo al cerrar; el esperado y la diferencia los revisa el administrador.</p>}
        <ResumenTurno turno={turno} />
        {movimiento && <MovimientoDialog tipo={movimiento} onHide={() => setMovimiento(null)} onRegistrado={() => { setMovimiento(null); notificar('Movimiento registrado correctamente.'); onCambio(); }} />}
        {cerrando && <CierreTurno ruta="/caja/cerrar" titulo={'Cerrar '+(turno.caja?.nombre ?? 'caja')} denominaciones={caja.denominaciones} onHide={() => setCerrando(false)}
            onCerrado={(cerradoTurno) => { setCerrando(false); notificar('Caja cerrada correctamente.'); onCerrado(cerradoTurno); }} />}
    </>;
}

function TurnoCerrado({ turno, onNueva }: { turno: TurnoCaja; onNueva: () => void }) {
    return <>
        <Card className="mb-3 border-success">
            <Card.Body className="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div><h2 className="h5 mb-1"><Icon name="check-circle" className="me-2 text-success" />Caja cerrada</h2>
                    <span className="text-body-secondary">{turno.caja?.nombre} · Turno {turno.numero} · {turno.cerrada_at ? formatearFechaHora(turno.cerrada_at) : ''}</span></div>
                <div className="d-flex flex-wrap gap-2">
                    <Link to={'/turnos/'+turno.id} className="btn btn-outline-secondary">Ver en historial</Link>
                    <Button onClick={onNueva}>Abrir otra caja</Button>
                </div>
            </Card.Body>
        </Card>
        <ResumenTurno turno={turno} />
    </>;
}

function MovimientoDialog({ tipo, onHide, onRegistrado }: { tipo: 'ingreso' | 'retiro'; onHide: () => void; onRegistrado: (movimiento: MovimientoCaja) => void }) {
    const formId = useId();
    const [monto, setMonto] = useState('');
    const [motivo, setMotivo] = useState('');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const formulario = useRef<HTMLFormElement>(null);

    useEffect(() => {
        if (Object.keys(errores).length) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const guardar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            const respuesta = await api.post<{ data: MovimientoCaja }>('/caja/movimientos', { tipo, monto: monto.trim() === '' ? null : Number(monto), motivo: motivo || null });
            onRegistrado(respuesta.data.data);
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(Object.keys(validacion).length ? '' : mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return <FormDialog title={tipo === 'ingreso' ? 'Registrar ingreso' : 'Registrar retiro'} size="md" onHide={onHide} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={onHide}>Cancelar</Button>
        <Button type="submit" form={formId} disabled={procesando}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Guardando…' : 'Registrar'}
        </Button>
    </>}>
        <form ref={formulario} id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void guardar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Movimiento de efectivo</legend>
                <TextField label="Monto (CLP)" type="number" min="1" step="1" inputMode="numeric" value={monto} required error={errores.monto} onChange={(event) => setMonto(event.target.value)} />
                <TextField label="Motivo" value={motivo} maxLength={150} required error={errores.motivo}
                    hint={tipo === 'ingreso' ? 'Por ejemplo: sencillo que dejaron en la caja.' : 'Por ejemplo: pago al proveedor de pan.'} onChange={(event) => setMotivo(event.target.value)} />
                {errores.tipo && <p className="text-danger" role="alert">{errores.tipo}</p>}
                {errorGeneral && <p className="text-danger" role="alert">{errorGeneral}</p>}
            </fieldset>
        </form>
    </FormDialog>;
}

