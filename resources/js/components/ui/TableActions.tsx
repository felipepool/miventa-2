import { useId } from 'react';
import { Dropdown } from 'react-bootstrap';
import { Link } from 'react-router-dom';
import Icon from '@/components/ui/Icon';

interface TableAction {
    label: string;
    icon: string;
    onClick: () => void;
    variant?: 'danger';
    disabled?: boolean;
    disabledReason?: string;
}

interface TableActionsProps {
    recordLabel: string;
    primaryAction: {
        label: string;
        icon: string;
        to: string;
    };
    actions: TableAction[];
}

/** Acción principal visible y acciones secundarias uniformes para todos los listados. */
export default function TableActions({ recordLabel, primaryAction, actions }: TableActionsProps) {
    const menuId = useId();

    return (
        <div className="table-actions">
            <Link to={primaryAction.to} className="btn btn-sm btn-outline-secondary table-actions__button"
                aria-label={`${primaryAction.label} a ${recordLabel}`}>
                <Icon name={primaryAction.icon} />
                {primaryAction.label}
            </Link>
            {actions.length > 0 && (
                <Dropdown align="end" focusFirstItemOnShow="keyboard">
                    <Dropdown.Toggle id={menuId} type="button" size="sm" variant="outline-secondary"
                        className="table-actions__button table-actions__toggle" aria-label={`Más acciones para ${recordLabel}`}>
                        <Icon name="three-dots-vertical" />
                    </Dropdown.Toggle>
                    <Dropdown.Menu className="table-actions__menu" role="menu" aria-labelledby={menuId}>
                        {actions.map((action, index) => (
                            <div key={action.label} role="none">
                                <Dropdown.Item as="button" type="button" role="menuitem" disabled={action.disabled}
                                    className={action.variant ? `table-actions__item--${action.variant}` : undefined}
                                    aria-describedby={action.disabled && action.disabledReason ? `${menuId}-reason-${index}` : undefined}
                                    onClick={action.onClick}>
                                    <Icon name={action.icon} />
                                    {action.label}
                                </Dropdown.Item>
                                {action.disabled && action.disabledReason && (
                                    <Dropdown.ItemText id={`${menuId}-reason-${index}`} className="table-actions__hint">
                                        {action.disabledReason}
                                    </Dropdown.ItemText>
                                )}
                            </div>
                        ))}
                    </Dropdown.Menu>
                </Dropdown>
            )}
        </div>
    );
}
