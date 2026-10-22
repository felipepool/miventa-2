import { useId } from 'react';
import { Form } from 'react-bootstrap';

interface CheckboxOption {
    value: string;
    label: string;
}

interface CheckboxListFieldProps {
    label: string;
    options: CheckboxOption[];
    value: string[];
    onChange: (value: string[]) => void;
    error?: string;
    hint?: string;
    disabled?: boolean;
}

/** Lista de selección múltiple con etiquetas completas como área táctil. */
export default function CheckboxListField({ label, options, value, onChange, error, hint, disabled }: CheckboxListFieldProps) {
    const groupId = useId();
    const descriptionId = error || hint ? `${groupId}-descripcion` : undefined;

    return (
        <fieldset className="mb-3" disabled={disabled} aria-describedby={descriptionId}>
            <legend className="form-label fs-6 mb-2">{label}</legend>
            <div className={`checkbox-list ${error ? 'is-invalid' : ''}`}>
                {options.map((option) => (
                    <Form.Check
                        key={option.value}
                        id={`${groupId}-${option.value}`}
                        label={option.label}
                        checked={value.includes(option.value)}
                        isInvalid={Boolean(error)}
                        aria-invalid={error ? true : undefined}
                        onChange={(event) => onChange(event.target.checked
                            ? [...value, option.value]
                            : value.filter((selected) => selected !== option.value))}
                    />
                ))}
            </div>
            {error ? (
                <div id={descriptionId} className="invalid-feedback d-block">{error}</div>
            ) : hint && (
                <Form.Text id={descriptionId} muted>{hint}</Form.Text>
            )}
        </fieldset>
    );
}
