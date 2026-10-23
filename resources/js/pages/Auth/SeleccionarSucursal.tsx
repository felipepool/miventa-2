import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Alert, Button, Form, Spinner } from 'react-bootstrap';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth, useSesion } from '@/lib/auth';
import { mensajeDeError } from '@/lib/http';
import { leerSucursalRecordada } from '@/lib/sucursalRecordada';
import { useTitulo } from '@/lib/useTitulo';

export default function SeleccionarSucursal() {
    useTitulo('Elegir sucursal');
    const { user, contexto } = useSesion();
    const { cambiarSucursal, refresh, logout, errorSesion } = useAuth();
    const location = useLocation();
    const [seleccion, setSeleccion] = useState(() => leerSucursalRecordada(user.id));
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const enCurso = useRef(false);
    const seleccionValida = contexto.sucursales.some((sucursal) => sucursal.id === seleccion) ? seleccion : '';
    const destino = (location.state as { desde?: string } | null)?.desde;

    if (contexto.sucursal) {
        return <Navigate to={destino?.startsWith('/') && !destino.startsWith('//') && !destino.startsWith('/seleccionar-sucursal') ? destino : '/dashboard'} replace />;
    }

    const ejecutar = async (accion: () => Promise<void>) => {
        if (enCurso.current) { return; }
        enCurso.current = true;
        setProcesando(true);
        setError('');
        try {
            await accion();
        } catch (exception) {
            setError(mensajeDeError(exception));
        } finally {
            enCurso.current = false;
            setProcesando(false);
        }
    };

    const confirmar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!seleccionValida) { return; }
        await ejecutar(async () => {
            try {
                await cambiarSucursal(seleccionValida);
            } catch (exception) {
                setSeleccion('');
                await refresh();
                setError('No se pudo ingresar a esa sucursal. Revisa las opciones disponibles e intenta nuevamente.');
            }
        });
    };

    return (
        <>
            <p className="small text-body-secondary mb-1 text-break">{contexto.negocio?.nombre} · {user.name}</p>
            <h1 className="h4 mb-2">{contexto.sucursales.length ? '¿En qué sucursal trabajarás?' : 'Sin sucursales disponibles'}</h1>
            <p className="text-body-secondary mb-3">{contexto.sucursales.length
                ? 'Elige tu sucursal para comenzar. Podrás cambiarla desde la cabecera.'
                : 'No tienes acceso a una sucursal activa. Contacta al administrador de tu negocio para continuar.'}</p>

            {(error || errorSesion) && <Alert variant="danger" role="alert">{error || errorSesion}</Alert>}

            {contexto.sucursales.length > 0 && (
                <form onSubmit={(event) => void confirmar(event)}>
                    <fieldset disabled={procesando} className="mb-3">
                        <legend className="visually-hidden">Sucursal de trabajo</legend>
                        <div className="branch-selection">
                            {contexto.sucursales.map((sucursal) => (
                                <label key={sucursal.id} className={`branch-selection__option ${seleccionValida === sucursal.id ? 'is-selected' : ''}`}>
                                    <Form.Check.Input type="radio" name="sucursal" value={sucursal.id} checked={seleccionValida === sucursal.id}
                                        onChange={() => { setSeleccion(sucursal.id); setError(''); }} />
                                    <span className="text-break">
                                        <span className="d-block fw-semibold">{sucursal.nombre}</span>
                                        {sucursal.direccion && <span className="d-block small text-body-secondary">{sucursal.direccion}</span>}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <Button type="submit" className="w-100" disabled={procesando || !seleccionValida}>
                        {procesando && <Spinner as="span" size="sm" className="me-2" aria-hidden="true" />}
                        Ingresar a esta sucursal
                    </Button>
                </form>
            )}

            <div className="d-flex flex-wrap gap-2 mt-3">
                <Button variant="outline-secondary" className="flex-grow-1" disabled={procesando} onClick={() => void ejecutar(refresh)}>Actualizar sucursales</Button>
                <Button variant="outline-secondary" className="flex-grow-1" disabled={procesando} onClick={() => void ejecutar(logout)}>Cerrar sesión</Button>
            </div>
        </>
    );
}
