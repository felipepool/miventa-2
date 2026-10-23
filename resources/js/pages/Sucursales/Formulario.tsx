import axios from 'axios';
import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Spinner } from 'react-bootstrap';
import { useLocation, useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { FormDialog, TextField } from '@/components/ui';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { useConsultaApi } from '@/lib/useConsultaApi';
import type { Sucursal } from '@/types/sucursal';
import AvisoCobro from './AvisoCobro';
import type { SucursalesContexto } from './Index';

export default function SucursalFormulario() {
    const { sucursalId } = useParams();
    const navigate = useNavigate();
    const location = useLocation();
    const formId = useId();
    const consulta = useConsultaApi<{ data: Sucursal }>(sucursalId ? '/sucursales/'+sucursalId : null);
    const { cobro, recargar, onGuardado } = useOutletContext<SucursalesContexto>();
    const [datos, setDatos] = useState({ id: '', nombre: '', direccion: '', telefono: '' });
    const [aceptado, setAceptado] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const formulario = useRef<HTMLFormElement>(null);
    const cerrar = () => navigate('/sucursales'+location.search, { replace: true });
    const noDisponible = axios.isAxiosError(consulta.error) && consulta.error.response?.status === 404;
    const cargando = consulta.cargando || Boolean(sucursalId && consulta.datos && datos.id !== consulta.datos.data.id);
    const tarifaNoDisponible = !sucursalId && (!cobro || cobro.total_mensual_uf === null);

    useEffect(() => {
        const sucursal = consulta.datos?.data;
        setDatos({ id: sucursal?.id ?? '', nombre: sucursal?.nombre ?? '', direccion: sucursal?.direccion ?? '', telefono: sucursal?.telefono ?? '' });
        setErrores({});
        setErrorGeneral('');
    }, [consulta.datos?.data]);
    useEffect(() => setAceptado(false), [cobro?.cotizacion]);
    useEffect(() => {
        if (Object.keys(errores).length > 0) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const guardar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current || cargando || consulta.error || tarifaNoDisponible) { return; }
        if (!sucursalId && cobro?.requiere_aceptacion && !aceptado) {
            setErrores({ acepto_cobro: 'Acepta el cobro mensual adicional para continuar.' });
            return;
        }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            const payload = { nombre: datos.nombre, direccion: datos.direccion, telefono: datos.telefono,
                ...(!sucursalId ? { acepto_cobro: aceptado, cotizacion: cobro?.cotizacion } : {}),
            };
            const respuesta = sucursalId ? await api.put<{ data: Sucursal }>('/sucursales/'+sucursalId, payload) : await api.post<{ data: Sucursal }>('/sucursales', payload);
            onGuardado(respuesta.data.data, sucursalId ? 'actualizada' : 'creada');
            cerrar();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(validacion.cotizacion ?? validacion.tarifa ?? (Object.keys(validacion).length === 0 ? mensajeDeError(exception) : ''));
            if (validacion.cotizacion || validacion.tarifa) { setAceptado(false); recargar(); }
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    return (
        <FormDialog title={sucursalId ? 'Editar sucursal' : 'Nueva sucursal'} size="md" onHide={cerrar} processing={procesando} actions={
            <>
                <Button variant="outline-secondary" disabled={procesando} onClick={cerrar}>Cancelar</Button>
                <Button type="submit" form={formId} disabled={procesando || cargando || Boolean(consulta.error) || tarifaNoDisponible}>
                    {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
                    {procesando ? 'Guardando…' : sucursalId ? 'Guardar cambios' : 'Crear sucursal'}
                </Button>
            </>
        }>
            {cargando ? (
                <div className="record-form" aria-busy="true">
                    <p role="status"><Spinner as="span" animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando sucursal…</p>
                    <TextField label="Nombre" disabled value="" /><TextField label="Dirección (opcional)" disabled value="" /><TextField label="Teléfono (opcional)" disabled value="" />
                </div>
            ) : consulta.error ? (
                <div role="alert"><p>{noDisponible ? 'La sucursal no existe o ya no está disponible en este negocio.' : 'No se pudo cargar la sucursal. '+mensajeDeError(consulta.error)}</p>
                    {!noDisponible && <Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button>}
                </div>
            ) : (
                <form ref={formulario} id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void guardar(event)}>
                    <fieldset disabled={procesando}>
                        <legend className="visually-hidden">Datos de la sucursal</legend>
                        <TextField label="Nombre" autoComplete="off" value={datos.nombre} maxLength={100} required error={errores.nombre} onChange={(event) => setDatos({ ...datos, nombre: event.target.value })} />
                        <TextField label="Dirección (opcional)" autoComplete="off" value={datos.direccion} maxLength={255} error={errores.direccion} onChange={(event) => setDatos({ ...datos, direccion: event.target.value })} />
                        <TextField label="Teléfono (opcional)" type="tel" autoComplete="off" value={datos.telefono} maxLength={30} error={errores.telefono} onChange={(event) => setDatos({ ...datos, telefono: event.target.value })} />
                        {errorGeneral && <p className="text-danger" role="alert">{errorGeneral}</p>}
                        {!sucursalId && (cobro ? <AvisoCobro cobro={cobro} aceptado={aceptado} onChange={(valor) => { setAceptado(valor); setErrores((actual) => ({ ...actual, acepto_cobro: '' })); }} disabled={procesando} error={errores.acepto_cobro} /> : <p role="status">Cargando condiciones del plan…</p>)}
                        {sucursalId && <p className="small text-body-secondary mb-0">Editar estos datos conserva las cajas, las asignaciones y el precio contratado.</p>}
                    </fieldset>
                </form>
            )}
        </FormDialog>
    );
}
