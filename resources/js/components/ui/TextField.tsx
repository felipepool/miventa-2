import { useId } from 'react';
import { Form } from 'react-bootstrap';
import type { FormControlProps } from 'react-bootstrap';
import type { InputHTMLAttributes } from 'react';

type TextFieldProps = Omit<FormControlProps, 'isInvalid'> &
    Omit<InputHTMLAttributes<HTMLInputElement>, 'size' | 'onChange' | 'value'> & {
        label: string;
        /** Filas visibles cuando se usa `as="textarea"`. */
        rows?: number;
        /** Mensaje de error de validación (ej. `form.errors.name`). */
        error?: string;
        hint?: string;
    };

/**
 * Campo de texto estándar: etiqueta, control, ayuda y error de validación.
 */
export default function TextField({ label, error, hint, id, className, ...controlProps }: TextFieldProps) {
    const generatedId = useId();
    const controlId = id ?? generatedId;

    return (
        <Form.Group controlId={controlId} className={className ?? 'mb-3'}>
            <Form.Label>{label}</Form.Label>
            <Form.Control isInvalid={Boolean(error)} {...controlProps} />
            {hint && !error && <Form.Text muted>{hint}</Form.Text>}
            <Form.Control.Feedback type="invalid">{error}</Form.Control.Feedback>
        </Form.Group>
    );
}
