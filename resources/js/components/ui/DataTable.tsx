import type { ReactNode } from 'react';
import { Card, Table } from 'react-bootstrap';
import Icon from '@/components/ui/Icon';
import Pagination from '@/components/ui/Pagination';
import type { PaginationMeta } from '@/types/api';

export interface Column<T> {
    header: string;
    cell: (row: T) => ReactNode;
    mobile?: 'title' | 'hidden';
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
    meta?: PaginationMeta;
    onPageChange: (page: number) => void;
}

/** Tabla de escritorio que usa las tarjetas del diseño original en pantallas pequeñas. */
export default function DataTable<T>({ columns, rows, rowKey, caption, toolbar, status, busy, emptyMessage, meta, onPageChange }: DataTableProps<T>) {
    return (
        <Card aria-busy={busy}>
            {toolbar && <Card.Header className="py-3">{toolbar}</Card.Header>}
            {status ?? (rows.length === 0 ? (
                <div className="empty-state" role="status">
                    <Icon name="people" />
                    {emptyMessage ?? 'No hay registros para mostrar.'}
                </div>
            ) : (
                <Table hover className="data-table align-middle">
                    <caption className="visually-hidden">{caption}</caption>
                    <thead>
                        <tr>
                            {columns.map((column) => <th key={column.header} scope="col">{column.header}</th>)}
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
                    <p className="small text-body-secondary mb-0" role="status">
                        Mostrando {meta.from}–{meta.to} de {meta.total}
                    </p>
                    {meta.last_page > 1 && <Pagination meta={meta} onPageChange={onPageChange} />}
                </Card.Footer>
            )}
        </Card>
    );
}
