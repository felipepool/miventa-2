import axios from 'axios';

/**
 * Cliente HTTP de la SPA. Usa la misma sesión que el login Blade:
 * axios envía la cookie XSRF-TOKEN como cabecera X-XSRF-TOKEN automáticamente.
 */
export const http = axios.create({
    baseURL: '/api',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
    withCredentials: true,
});

http.interceptors.response.use(
    (response) => response,
    (error: unknown) => {
        // Sesión expirada: volver al login.
        if (axios.isAxiosError(error) && error.response?.status === 401) {
            window.location.href = '/login';
        }
        return Promise.reject(error);
    },
);
