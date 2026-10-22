import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { api, onSesionExpirada, prepararCsrf, web } from '@/lib/http';
import type { Sesion } from '@/types/user';

export interface Credenciales {
    codigo_negocio: string;
    username: string;
    password: string;
    remember: boolean;
}

interface AuthContextValue {
    /** `undefined` mientras se consulta la sesión; `null` si no hay sesión. */
    sesion: Sesion | null | undefined;
    login: (credenciales: Credenciales) => Promise<void>;
    logout: () => Promise<void>;
    refresh: () => Promise<void>;
    cambiarSucursal: (sucursalId: string) => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

/**
 * Sesión del usuario: la consulta al iniciar la SPA y expone login/logout y el
 * cambio de sucursal activa.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
    const [sesion, setSesion] = useState<Sesion | null | undefined>(undefined);

    const refresh = useCallback(async () => {
        try {
            const response = await api.get<{ data: Sesion }>('/me');
            setSesion(response.data.data);
        } catch {
            setSesion(null);
        }
    }, []);

    const login = useCallback(
        async (credenciales: Credenciales) => {
            await prepararCsrf();
            await web.post('/login', credenciales);
            await refresh();
        },
        [refresh],
    );

    const logout = useCallback(async () => {
        try {
            await web.post('/logout');
        } finally {
            setSesion(null);
        }
    }, []);

    const cambiarSucursal = useCallback(async (sucursalId: string) => {
        const response = await api.put<{ data: Sesion }>(`/sucursal-actual/${sucursalId}`);
        setSesion(response.data.data);
    }, []);

    useEffect(() => {
        onSesionExpirada(() => setSesion(null));
        void refresh();

        return () => onSesionExpirada(null);
    }, [refresh]);

    const value = useMemo(() => ({ sesion, login, logout, refresh, cambiarSucursal }), [sesion, login, logout, refresh, cambiarSucursal]);

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
