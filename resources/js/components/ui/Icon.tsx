interface IconProps {
    /** Nombre de Bootstrap Icons sin el prefijo `bi-` (ej. `cart3`). */
    name: string;
    className?: string;
}

export default function Icon({ name, className = '' }: IconProps) {
    return <i className={`bi bi-${name} ${className}`.trim()} aria-hidden="true" />;
}
