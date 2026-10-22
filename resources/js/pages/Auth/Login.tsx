import { useState } from 'react';
import type { FormEvent } from 'react';
import { Alert, Button, Form, Spinner } from 'react-bootstrap';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { TextField } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import type { Credenciales } from '@/lib/auth';
import { erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { useTitulo } from '@/lib/useTitulo';

const CLAVE_NEGOCIO_RECORDADO = 'miventa.codigo_negocio';

function leerNegocioRecordado(): string {
    try {
        return window.localStorage.getItem(CLAVE_NEGOCIO_RECORDADO) ?? '';
    } catch {
        return '';
    }
}

function guardarNegocioRecordado(codigo: string | null): void {
    try {
        if (codigo) {
            window.localStorage.setItem(CLAVE_NEGOCIO_RECORDADO, codigo);
        } else {
            window.localStorage.removeItem(CLAVE_NEGOCIO_RECORDADO);
        }
    } catch {
        // Sin almacenamiento disponible (modo privado): simplemente no se recuerda.
    }
}

export default function Login() {
    useTitulo('Iniciar sesión');

    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const destino = (location.state as { desde?: string } | null)?.desde ?? '/dashboard';

    const [negocioRecordado] = useState(leerNegocioRecordado);
    const [datos, setDatos] = useState<Credenciales>({ codigo_negocio: negocioRecordado, username: '', password: '', remember: false });
    const [recordarNegocio, setRecordarNegocio] = useState(negocioRecordado !== '');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [procesando, setProcesando] = useState(false);

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcesando(true);
        setErrores({});
        setError(null);

        try {
            await login(datos);
            guardarNegocioRecordado(recordarNegocio ? datos.codigo_negocio.trim().toLowerCase() : null);
            navigate(destino, { replace: true });
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);

            if (Object.keys(validacion).length === 0) {
                setError(mensajeDeError(exception));
            }

            setDatos((actual) => ({ ...actual, password: '' }));
            setProcesando(false);
        }
    };

    return (
        <>
            <h1 className="h5 mb-1">Iniciar sesión</h1>
            <p className="text-muted mb-4">Ingresa los datos de tu negocio para continuar.</p>

            {error && (
                <Alert variant="danger" className="small">
                    {error}
                </Alert>
            )}

            <form onSubmit={(event) => void submit(event)} noValidate>
                <TextField
                    label="Código de negocio"
                    autoComplete="organization"
                    autoCapitalize="none"
                    spellCheck={false}
                    autoFocus={negocioRecordado === ''}
                    value={datos.codigo_negocio}
                    onChange={(event) => setDatos({ ...datos, codigo_negocio: event.target.value })}
                    error={errores.codigo_negocio}
                />

                <TextField
                    label="Usuario"
                    autoComplete="username"
                    autoCapitalize="none"
                    spellCheck={false}
                    autoFocus={negocioRecordado !== ''}
                    value={datos.username}
                    onChange={(event) => setDatos({ ...datos, username: event.target.value })}
                    error={errores.username}
                />

                <TextField
                    label="Contraseña"
                    type="password"
                    autoComplete="current-password"
                    value={datos.password}
                    onChange={(event) => setDatos({ ...datos, password: event.target.value })}
                    error={errores.password}
                />

                <Form.Check
                    id="recordar_negocio"
                    className="mb-2"
                    label="Recordar este negocio en este equipo"
                    checked={recordarNegocio}
                    onChange={(event) => setRecordarNegocio(event.target.checked)}
                />

                <Form.Check
                    id="remember"
                    className="mb-4"
                    label="Mantener sesión iniciada"
                    checked={datos.remember}
                    onChange={(event) => setDatos({ ...datos, remember: event.target.checked })}
                />

                <Button type="submit" variant="primary" className="w-100" disabled={procesando}>
                    {procesando && <Spinner size="sm" className="me-2" />}
                    Ingresar
                </Button>
            </form>

            <p className="text-center small mt-3 mb-0">
                <Link to="/olvide-contrasena">¿Olvidaste tu contraseña?</Link>
            </p>
        </>
    );
}
