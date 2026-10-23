import { useCallback, useEffect, useState } from 'react';
import { api } from '@/lib/http';

interface Resultado<T> {
    ruta: string;
    datos?: T;
    error?: unknown;
}

/** Cancela consultas anteriores para que una respuesta lenta no reemplace los filtros actuales. */
export function useConsultaApi<T>(ruta: string | null) {
    const [resultado, setResultado] = useState<Resultado<T>>();
    const [intento, setIntento] = useState(0);
    const recargar = useCallback(() => setIntento((actual) => actual + 1), []);

    useEffect(() => {
        const controller = new AbortController();
        setResultado(undefined);

        if (ruta === null) {
            return () => controller.abort();
        }

        void api.get<T>(ruta, { signal: controller.signal }).then(
            (response) => {
                if (!controller.signal.aborted) {
                    setResultado({ ruta, datos: response.data });
                }
            },
            (error: unknown) => {
                if (!controller.signal.aborted) {
                    setResultado({ ruta, error });
                }
            },
        );

        return () => controller.abort();
    }, [ruta, intento]);

    const vigente = resultado?.ruta === ruta ? resultado : undefined;

    return { datos: vigente?.datos, error: vigente?.error, cargando: ruta !== null && vigente === undefined, recargar };
}
