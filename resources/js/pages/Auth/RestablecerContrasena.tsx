import { useState } from 'react';
import type { FormEvent } from 'react';
import { Alert, Button, Spinner } from 'react-bootstrap';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Icon, TextField } from '@/components/ui';
import { erroresDeValidacion, mensajeDeError, prepararCsrf, web } from '@/lib/http';
import { useTitulo } from '@/lib/useTitulo';

/**
 * Crea la contraseña nueva desde el enlace que llegó por correo.
 */
export default function RestablecerContrasena() {
    useTitulo('Crear contraseña nueva');

    const { token = '' } = useParams();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    const [datos, setDatos] = useState({ email: searchParams.get('email') ?? '', password: '', password_confirmation: '' });
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [listo, setListo] = useState<string | null>(null);
    const [procesando, setProcesando] = useState(false);

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcesando(true);
        setErrores({});
        setError(null);

        try {
            await prepararCsrf();
            const response = await web.post<{ message: string }>('/reset-password', { ...datos, token });
            setListo(response.data.message);
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);

            if (Object.keys(validacion).length === 0) {
                setError(mensajeDeError(exception));
            }

            setDatos((actual) => ({ ...actual, password: '', password_confirmation: '' }));
        } finally {
            setProcesando(false);
        }
    };

    if (listo) {
        return (
            <>
                <h1 className="h5 mb-3">Contraseña actualizada</h1>
                <Alert variant="success" className="small">
                    <Icon name="check-circle" className="me-1" />
                    {listo}
                </Alert>
                <Button variant="primary" className="w-100" onClick={() => navigate('/login', { replace: true })}>
                    Iniciar sesión
                </Button>
            </>
        );
    }

    return (
        <>
            <h1 className="h5 mb-1">Crea tu contraseña nueva</h1>
            <p className="text-muted mb-4">Usa al menos 8 caracteres. Después ingresarás con ella.</p>

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
                    autoComplete="email"
                    autoCapitalize="none"
                    value={datos.email}
                    onChange={(event) => setDatos({ ...datos, email: event.target.value })}
                    error={errores.email ?? errores.token}
                />

                <TextField
                    label="Contraseña nueva"
                    type="password"
                    autoComplete="new-password"
                    autoFocus
                    value={datos.password}
                    onChange={(event) => setDatos({ ...datos, password: event.target.value })}
                    error={errores.password}
                />

                <TextField
                    label="Repite la contraseña"
                    type="password"
                    autoComplete="new-password"
                    value={datos.password_confirmation}
                    onChange={(event) => setDatos({ ...datos, password_confirmation: event.target.value })}
                    error={errores.password_confirmation}
                />

                <Button type="submit" variant="primary" className="w-100" disabled={procesando}>
                    {procesando && <Spinner size="sm" className="me-2" />}
                    Guardar contraseña
                </Button>
            </form>

            <p className="text-center small mt-4 mb-0">
                <Link to="/olvide-contrasena">Pedir otro enlace</Link> · <Link to="/login">Iniciar sesión</Link>
            </p>
        </>
    );
}
