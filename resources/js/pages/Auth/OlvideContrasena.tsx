import { useState } from 'react';
import type { FormEvent } from 'react';
import { Alert, Button, Spinner } from 'react-bootstrap';
import { Link } from 'react-router-dom';
import { TextField } from '@/components/ui';
import { erroresDeValidacion, mensajeDeError, prepararCsrf, web } from '@/lib/http';
import { useTitulo } from '@/lib/useTitulo';
import { notificar } from '@/lib/notificaciones';

/**
 * Pide el enlace para crear una contraseña nueva. La respuesta es la misma
 * exista o no la cuenta, para no revelar qué correos están registrados.
 */
export default function OlvideContrasena() {
    useTitulo('Recuperar contraseña');

    const [email, setEmail] = useState('');
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [procesando, setProcesando] = useState(false);

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcesando(true);
        setErrores({});
        setError(null);

        try {
            await prepararCsrf();
            const response = await web.post<{ message: string }>('/forgot-password', { email });
            notificar(response.data.message);
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);

            if (Object.keys(validacion).length === 0) {
                setError(mensajeDeError(exception));
            }
        } finally {
            setProcesando(false);
        }
    };

    return (
        <>
            <h1 className="h5 mb-1">¿Olvidaste tu contraseña?</h1>
            <p className="text-muted mb-4">Escribe el correo de tu cuenta y te enviaremos un enlace para crear una nueva.</p>

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
                    autoFocus
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    error={errores.email}
                />

                <Button type="submit" variant="primary" className="w-100" disabled={procesando}>
                    {procesando && <Spinner size="sm" className="me-2" />}
                    Enviar enlace
                </Button>
            </form>

            <p className="small text-muted mt-4 mb-2">Si tu cuenta no tiene correo, pídele a un administrador de tu negocio que te cambie la contraseña desde Usuarios.</p>

            <p className="text-center small mb-0">
                <Link to="/login">Volver a iniciar sesión</Link>
            </p>
        </>
    );
}
