import { useEffect, useRef, useState } from 'react';
import { Form, InputGroup } from 'react-bootstrap';
import Icon from '@/components/ui/Icon';

interface SearchInputProps {
    value: string;
    onChange: (value: string) => void;
    label: string;
    placeholder?: string;
    maxLength?: number;
}

/** Espera una pausa al escribir; la tecla Buscar del teclado táctil aplica de inmediato. */
export default function SearchInput({ value, onChange, label, placeholder = 'Buscar…', maxLength }: SearchInputProps) {
    const [texto, setTexto] = useState(value);
    const temporizador = useRef<number | undefined>(undefined);
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    useEffect(() => {
        window.clearTimeout(temporizador.current);
        setTexto(value);
    }, [value]);

    useEffect(() => () => window.clearTimeout(temporizador.current), []);

    const buscar = (nuevoTexto: string, inmediato = false) => {
        window.clearTimeout(temporizador.current);
        setTexto(nuevoTexto);

        if (inmediato) {
            onChangeRef.current(nuevoTexto);
        } else {
            temporizador.current = window.setTimeout(() => onChangeRef.current(nuevoTexto), 350);
        }
    };

    return (
        <InputGroup>
            <InputGroup.Text className="bg-white"><Icon name="search" /></InputGroup.Text>
            <Form.Control
                type="search"
                enterKeyHint="search"
                autoComplete="off"
                value={texto}
                aria-label={label}
                placeholder={placeholder}
                maxLength={maxLength}
                onChange={(event) => buscar(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        buscar(texto, true);
                    }
                }}
            />
        </InputGroup>
    );
}
