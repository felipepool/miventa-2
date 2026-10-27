import { useId } from 'react';
import type { ReactNode } from 'react';
import { Card, Form, Table } from 'react-bootstrap';
import Icon from '@/components/ui/Icon';
import Pagination from '@/components/ui/Pagination';
import type { PaginationMeta } from '@/types/api';

export interface Column<T> {
    header: string;
    cell: (row: T) => ReactNode;
    mobile?: 'title' | 'hidden' | 'actions';
}

interface DataTableProps<T> {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string;
    caption: string;
    toolbar?: ReactNode;
    status?: ReactNode;
    busy?: boolean;
    emptyMessage?: string;
    emptyAction?: ReactNode;
    emptyIcon?: string;
    meta?: PaginationMeta;
    onPageChange: (page: number) => void;
    /** Activa el selector de registros por página con las cantidades que acepta la API. */
    onPerPageChange?: (perPage: number) => void;
}

/** Cantidades de registros por página que aceptan los listados con selector. */
export const POR_PAGINA = [5, 10, 25, 50, 100] as const;

/** Tabla de escritorio que usa las tarjetas del diseño original en pantallas pequeñas. */
export default function DataTable<T>({ columns, rows, rowKey, caption, toolbar, status, busy, emptyMessage, emptyAction, emptyIcon = 'people', meta, onPageChange, onPerPageChange }: DataTableProps<T>) {
    const idPorPagina = useId();

    return (
        <Card aria-busy={busy}>
            {toolbar && <Card.Header className="py-3">{toolbar}</Card.Header>}
            {status ?? (rows.length === 0 ? (
                <div className="empty-state" role="status">
                    <Icon name={emptyIcon} />
                    <p className="mb-0">{emptyMessage ?? 'No hay registros para mostrar.'}</p>
                    {emptyAction && <div className="mt-3">{emptyAction}</div>}
                </div>
            ) : (
                <Table hover className="data-table align-middle">
                    <caption className="visually-hidden">{caption}</caption>
                    <thead>
                        <tr>
                            {columns.map((column) => (
                                <th key={column.header} scope="col" className={column.mobile === 'actions' ? 'data-table__cell--actions' : undefined}>
                                    {column.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={rowKey(row)}>
                                {columns.map((column) => (
                                    <td key={column.header} data-label={column.header} className={column.mobile ? `data-table__cell--${column.mobile}` : undefined}>
                                        <div className="data-table__value">{column.cell(row)}</div>
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </Table>
            ))}
            {!status && meta && meta.total > 0 && (
                <Card.Footer className="py-3">
                    <div className="table-footer">
                        <p className="small text-body-secondary mb-0" role="status">
                            Mostrando {meta.from}–{meta.to} de {meta.total}
                        </p>
                        {onPerPageChange && meta.total > POR_PAGINA[0] && (
                            <Form.Group controlId={idPorPagina} className="table-footer__per-page">
                                <Form.Label className="small text-body-secondary mb-0">Por página</Form.Label>
                                <Form.Select value={meta.per_page} onChange={(event) => onPerPageChange(Number(event.target.value))}>
                                    {POR_PAGINA.map((cantidad) => <option key={cantidad} value={cantidad}>{cantidad}</option>)}
                                </Form.Select>
                            </Form.Group>
                        )}
                    </div>
                    {meta.last_page > 1 && <Pagination meta={meta} onPageChange={onPageChange} />}
                </Card.Footer>
            )}
        </Card>
    );
}
