import axios from 'axios';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { api, mensajeDeError, onSesionExpirada, onSucursalRequerida, prepararCsrf, web } from '@/lib/http';
import { guardarSucursalRecordada } from '@/lib/sucursalRecordada';
import type { Sesion, User } from '@/types/user';

export interface Credenciales {
    codigo_negocio: string;
    username: string;
    password: string;
    remember: boolean;
}

interface AuthContextValue {
    /** `undefined` mientras se consulta la sesión; `null` si no hay sesión. */
    sesion: Sesion | null | undefined;
    errorSesion: string | null;
    login: (credenciales: Credenciales) => Promise<void>;
    logout: () => Promise<void>;
    refresh: () => Promise<void>;
    cambiarSucursal: (sucursalId: string) => Promise<void>;
    actualizarUsuario: (usuario: User) => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

/**
 * Sesión del usuario: la consulta al iniciar la SPA y expone login/logout y el
 * cambio de sucursal activa.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
    const [sesion, setSesion] = useState<Sesion | null | undefined>(undefined);
    const [errorSesion, setErrorSesion] = useState<string | null>(null);
    const revision = useRef(0);

    const recibirSesion = useCallback((actual: Sesion) => {
        setSesion(actual);
        setErrorSesion(null);
        if (actual.contexto.sucursal) {
            guardarSucursalRecordada(actual.user.id, actual.contexto.sucursal.id);
        }
    }, []);

    const refresh = useCallback(async () => {
        const intento = ++revision.current;
        setErrorSesion(null);
        try {
            const response = await api.get<{ data: Sesion }>('/me');
            if (intento === revision.current) { recibirSesion(response.data.data); }
        } catch (error) {
            if (intento === revision.current) {
                if (axios.isAxiosError(error) && [401, 419].includes(error.response?.status ?? 0)) {
                    setSesion(null);
                } else {
                    setErrorSesion(mensajeDeError(error));
                    throw error;
                }
            }
        }
    }, [recibirSesion]);

    const login = useCallback(
        async (credenciales: Credenciales) => {
            await prepararCsrf();
            await web.post('/login', credenciales);
            await refresh();
        },
        [refresh],
    );

    const logout = useCallback(async () => {
        await web.post('/logout');
        revision.current++;
        setSesion(null);
        setErrorSesion(null);
    }, []);

    const cambiarSucursal = useCallback(async (sucursalId: string) => {
        const intento = ++revision.current;
        const response = await api.put<{ data: Sesion }>(`/sucursal-actual/${sucursalId}`);
        if (intento === revision.current) { recibirSesion(response.data.data); }
    }, [recibirSesion]);

    useEffect(() => {
        onSesionExpirada(() => { revision.current++; setSesion(null); setErrorSesion(null); });
        onSucursalRequerida(() => {
            setSesion((actual) => actual ? { ...actual, contexto: { ...actual.contexto, sucursal: null } } : actual);
            void refresh().catch(() => {});
        });
        void refresh().catch(() => {});

        return () => { onSesionExpirada(null); onSucursalRequerida(null); };
    }, [refresh]);

    const actualizarUsuario = useCallback((usuario: User) => {
        setSesion((actual) => actual?.user.id === usuario.id ? {
            ...actual,
            user: { id: usuario.id, name: usuario.name, username: usuario.username, email: usuario.email },
        } : actual);
    }, []);

    const value = useMemo(() => ({ sesion, errorSesion, login, logout, refresh, cambiarSucursal, actualizarUsuario }), [sesion, errorSesion, login, logout, refresh, cambiarSucursal, actualizarUsuario]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error('useAuth debe usarse dentro de <AuthProvider>.');
    }

    return context;
}

/**
 * Sesión del usuario en páginas protegidas (RequiereSesion garantiza que existe).
 */
export function useSesion(): Sesion {
    const { sesion } = useAuth();

    if (!sesion) {
        throw new Error('useSesion solo se puede usar en rutas protegidas.');
    }

    return sesion;
}
