import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { api, onSesionExpirada, prepararCsrf, web } from '@/lib/http';
import type { User } from '@/types/user';

export interface Credenciales {
    email: string;
    password: string;
    remember: boolean;
}

interface AuthContextValue {
    /** `undefined` mientras se consulta la sesión; `null` si no hay sesión. */
    user: User | null | undefined;
    login: (credenciales: Credenciales) => Promise<void>;
    logout: () => Promise<void>;
    refresh: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

/**
 * Sesión del usuario: la consulta al iniciar la SPA y expone login/logout.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null | undefined>(undefined);

    const refresh = useCallback(async () => {
        try {
            const response = await api.get<{ data: User }>('/me');
            setUser(response.data.data);
        } catch {
            setUser(null);
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
            setUser(null);
        }
    }, []);

    useEffect(() => {
        onSesionExpirada(() => setUser(null));
        void refresh();

        return () => onSesionExpirada(null);
    }, [refresh]);

    const value = useMemo(() => ({ user, login, logout, refresh }), [user, login, logout, refresh]);

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
 * Usuario autenticado en páginas protegidas (RequireAuth garantiza que existe).
 */
export function useUser(): User {
    const { user } = useAuth();

    if (!user) {
        throw new Error('useUser solo se puede usar en rutas protegidas.');
    }

    return user;
}
