import { useId } from 'react';
import type { ReactNode } from 'react';
import { Button, Modal, Spinner } from 'react-bootstrap';

interface ConfirmModalProps {
    title: string;
    children: ReactNode;
    confirmLabel: string;
    variant?: 'danger' | 'primary';
    processing?: boolean;
    error?: string;
    confirmDisabled?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export default function ConfirmModal({ title, children, confirmLabel, variant = 'primary', processing = false, error, confirmDisabled = false, onConfirm, onCancel }: ConfirmModalProps) {
    const titleId = useId();
    const descriptionId = useId();

    return (
        <Modal show centered scrollable onHide={() => { if (!processing) { onCancel(); } }} keyboard={!processing}
            backdrop={processing ? 'static' : true} aria-labelledby={titleId} aria-describedby={descriptionId} className="form-dialog">
            <Modal.Header closeButton={!processing} closeLabel="Cerrar">
                <Modal.Title as="h2" id={titleId}>{title}</Modal.Title>
            </Modal.Header>
            <Modal.Body>
                <div id={descriptionId}>{children}</div>
                {error && <p role="alert" className="text-danger mt-3 mb-0">{error}</p>}
            </Modal.Body>
            <Modal.Footer className="form-dialog__footer">
                <Button autoFocus variant="outline-secondary" disabled={processing} onClick={onCancel}>Cancelar</Button>
                <Button variant={variant} disabled={processing || confirmDisabled} onClick={onConfirm}>
                    {processing && <Spinner as="span" animation="border" size="sm" className="me-2" aria-hidden="true" />}
                    {processing ? 'Guardando…' : confirmLabel}
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
