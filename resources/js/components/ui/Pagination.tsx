import { Button } from 'react-bootstrap';
import type { PaginationMeta } from '@/types/api';

interface PaginationProps {
    meta: PaginationMeta;
    onPageChange: (page: number) => void;
}

/** Navega por la SPA; las URLs de paginación de la API no se abren como páginas. */
export default function Pagination({ meta, onPageChange }: PaginationProps) {
    return (
        <nav className="table-pagination" aria-label="Paginación">
            <Button
                variant="outline-secondary"
                disabled={meta.current_page <= 1}
                onClick={() => onPageChange(meta.current_page - 1)}
                aria-label="Página anterior"
            >
                Anterior
            </Button>
            <span className="small text-body-secondary">Página {meta.current_page} de {meta.last_page}</span>
            <Button
                variant="outline-secondary"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => onPageChange(meta.current_page + 1)}
                aria-label="Página siguiente"
            >
                Siguiente
            </Button>
        </nav>
    );
}
