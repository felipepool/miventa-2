import { useState } from 'react';
import type { FormEvent } from 'react';
import { Alert, Button, Form, Spinner } from 'react-bootstrap';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { TextField } from '@/components/ui';
import { useAuth } from '@/lib/auth';
import type { Credenciales } from '@/lib/auth';
import { erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { useTitulo } from '@/lib/useTitulo';

export default function Login() {
    useTitulo('Iniciar sesión');

    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const destino = (location.state as { desde?: string } | null)?.desde ?? '/dashboard';

    const [datos, setDatos] = useState<Credenciales>({ email: '', password: '', remember: false });
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
            <p className="text-muted mb-4">Ingresa tus datos para continuar.</p>

            {error && (
                <Alert variant="danger" className="small">
                    {error}
                </Alert>
            )}

            <form onSubmit={(event) => void submit(event)} noValidate>
                <TextField
                    label="Correo electrónico"
                    type="email"
                    inputMode="email"
                    autoComplete="username"
                    autoCapitalize="none"
                    spellCheck={false}
                    autoFocus
                    value={datos.email}
                    onChange={(event) => setDatos({ ...datos, email: event.target.value })}
                    error={errores.email}
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
