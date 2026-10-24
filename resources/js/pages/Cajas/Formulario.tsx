import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Form, Spinner } from 'react-bootstrap';
import { useLocation, useNavigate, useOutletContext } from 'react-router-dom';
import { FormDialog, TextField } from '@/components/ui';
import { useSesion } from '@/lib/auth';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Caja, CobroCaja } from '@/types/caja';
import AvisoCobro from './AvisoCobro';
import type { CajasContexto } from './Index';

export default function CajaFormulario({ caja }: { caja?: Caja }) {
    const { contexto } = useSesion();
    const { sucursales, errorListado, recargar, onGuardado } = useOutletContext<CajasContexto>();
    const location = useLocation();
    const navigate = useNavigate();
    const formId = useId();
    const selectId = useId();
    const formulario = useRef<HTMLFormElement>(null);
    const [nombre, setNombre] = useState(caja?.nombre ?? '');
    const [sucursalId, setSucursalId] = useState(() => {
        const filtro = new URLSearchParams(location.search).get('sucursal');
        return sucursales?.find((sucursal) => sucursal.id === filtro && sucursal.activa)?.id
            ?? sucursales?.find((sucursal) => sucursal.id === contexto.sucursal?.id && sucursal.activa)?.id ?? '';
    });
    const [aceptado, setAceptado] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const cotizacion = useConsultaApi<{ data: CobroCaja }>(!caja && sucursalId ? '/cajas/cotizacion?sucursal='+sucursalId : null);
    const cobro = cotizacion.datos?.data;
    const cerrar = () => navigate('/cajas'+location.search, { replace: true });
    const sucursalActiva = sucursales?.some((sucursal) => sucursal.id === sucursalId && sucursal.activa);
    const bloqueaCreacion = !caja && (!sucursalActiva || !cobro || cotizacion.cargando || Boolean(cotizacion.error) || cobro.total_mensual_uf === null);

    useEffect(() => setAceptado(false), [sucursalId, cobro?.cotizacion]);
    useEffect(() => {
        if (Object.keys(errores).length) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const guardar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current || bloqueaCreacion) { return; }
        if (!caja && cobro?.requiere_aceptacion && !aceptado) {
            setErrores({ acepto_cobro: 'Acepta el cobro mensual adicional para continuar.' });
            return;
        }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            const payload = { nombre, ...(!caja ? { sucursal: sucursalId, acepto_cobro: aceptado, cotizacion: cobro?.cotizacion } : {}) };
            const respuesta = caja ? await api.put<{ data: Caja }>('/cajas/'+caja.id, payload) : await api.post<{ data: Caja }>('/cajas', payload);
            onGuardado(respuesta.data.data, caja ? 'actualizada' : 'creada');
            cerrar();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(validacion.cotizacion ?? validacion.tarifa ?? (Object.keys(validacion).length ? '' : mensajeDeError(exception)));
            if (validacion.cotizacion || validacion.tarifa) { setAceptado(false); cotizacion.recargar(); }
            if (validacion.sucursal) { recargar(); }
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return <FormDialog title={caja ? 'Editar caja' : 'Nueva caja'} size="md" onHide={cerrar} processing={procesando} actions={<>
        <Button variant="outline-secondary" disabled={procesando} onClick={cerrar}>Cancelar</Button>
        <Button type="submit" form={formId} disabled={procesando || bloqueaCreacion}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Guardando…' : caja ? 'Guardar cambios' : 'Crear caja'}
        </Button>
    </>}>
        <form ref={formulario} id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void guardar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Datos de la caja</legend>
                {caja ? <p className="small text-body-secondary">Sucursal: <strong>{caja.sucursal.nombre}</strong>. La caja conserva su sucursal e historial.</p>
                    : <Form.Group className="mb-3" controlId={selectId}>
                        <Form.Label>Sucursal</Form.Label>
                        <Form.Select value={sucursalId} required isInvalid={Boolean(errores.sucursal)} aria-invalid={errores.sucursal ? true : undefined}
                            aria-describedby={errores.sucursal ? selectId+'-error' : undefined} onChange={(event) => { setSucursalId(event.target.value); setErrores({}); setErrorGeneral(''); }}>
                            <option value="">Selecciona una sucursal</option>
                            {sucursales?.filter((sucursal) => sucursal.activa).map((sucursal) => <option key={sucursal.id} value={sucursal.id}>{sucursal.nombre}</option>)}
                        </Form.Select>
                        {errores.sucursal && <Form.Control.Feedback id={selectId+'-error'} type="invalid">{errores.sucursal}</Form.Control.Feedback>}
                        {!sucursales && (errorListado ? <div role="alert" className="mt-2"><p>No se pudieron cargar las sucursales.</p><Button variant="outline-secondary" onClick={recargar}>Reintentar</Button></div> : <p role="status">Cargando sucursales…</p>)}
                        {sucursales && !sucursales.some((sucursal) => sucursal.activa) && <p role="alert">Activa una sucursal antes de crear cajas.</p>}
                    </Form.Group>}
                <TextField label="Nombre" autoComplete="off" value={nombre} maxLength={50} required error={errores.nombre} onChange={(event) => setNombre(event.target.value)} />
                {errorGeneral && <p className="text-danger" role="alert">{errorGeneral}</p>}
                {!caja && sucursalId && (cotizacion.cargando ? <p role="status">Consultando condiciones del cobro…</p>
                    : cotizacion.error ? <div role="alert"><p>No se pudo consultar el cobro.</p><Button variant="outline-secondary" onClick={cotizacion.recargar}>Reintentar</Button></div>
                    : cobro && <AvisoCobro cobro={cobro} aceptado={aceptado} disabled={procesando} error={errores.acepto_cobro}
                        onChange={(valor) => { setAceptado(valor); setErrores((actual) => ({ ...actual, acepto_cobro: '' })); }} />)}
            </fieldset>
        </form>
    </FormDialog>;
}
