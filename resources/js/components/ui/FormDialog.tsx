import { useEffect, useId, useState } from 'react';
import type { ReactNode } from 'react';
import { Modal, Offcanvas } from 'react-bootstrap';

interface FormDialogProps {
    title: string;
    onHide: () => void;
    processing?: boolean;
    children: ReactNode;
    actions: ReactNode;
}

const consultaTactil = '(max-width: 1199.98px), (any-pointer: coarse)';

/** Mantiene el mismo formulario al alternar entre escritorio y pantallas táctiles. */
export default function FormDialog({ title, onHide, processing = false, children, actions }: FormDialogProps) {
    const titleId = useId();
    const [tactil, setTactil] = useState(() => window.matchMedia(consultaTactil).matches);

    useEffect(() => {
        const consulta = window.matchMedia(consultaTactil);
        const actualizar = () => setTactil(consulta.matches);
        consulta.addEventListener('change', actualizar);
        actualizar();

        return () => consulta.removeEventListener('change', actualizar);
    }, []);

    const cerrar = () => {
        if (!processing) {
            onHide();
        }
    };

    if (tactil) {
        return (
            <Offcanvas show placement="end" onHide={cerrar} keyboard={!processing} backdrop={processing ? 'static' : true}
                className="form-dialog form-dialog--offcanvas" aria-labelledby={titleId}>
                <Offcanvas.Header closeButton={!processing} closeLabel="Cerrar">
                    <Offcanvas.Title as="h2" id={titleId}>{title}</Offcanvas.Title>
                </Offcanvas.Header>
                <Offcanvas.Body>{children}</Offcanvas.Body>
                <div className="form-dialog__footer">{actions}</div>
            </Offcanvas>
        );
    }

    return (
        <Modal show centered scrollable size="lg" onHide={cerrar} keyboard={!processing} backdrop={processing ? 'static' : true}
            className="form-dialog" aria-labelledby={titleId}>
            <Modal.Header closeButton={!processing} closeLabel="Cerrar">
                <Modal.Title as="h2" id={titleId}>{title}</Modal.Title>
            </Modal.Header>
            <Modal.Body>{children}</Modal.Body>
            <Modal.Footer className="form-dialog__footer">{actions}</Modal.Footer>
        </Modal>
    );
}
