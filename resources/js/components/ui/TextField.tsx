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
            <Form.Control
                isInvalid={Boolean(error)}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${controlId}-error` : hint ? `${controlId}-ayuda` : undefined}
                {...controlProps}
            />
            {hint && !error && <Form.Text id={`${controlId}-ayuda`} muted>{hint}</Form.Text>}
            {error && <Form.Control.Feedback id={`${controlId}-error`} type="invalid">{error}</Form.Control.Feedback>}
        </Form.Group>
    );
}
