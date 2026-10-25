import { useState } from 'react';
import axios from 'axios';
import { Button, Spinner } from 'react-bootstrap';
import { Link, useParams } from 'react-router-dom';
import CierreTurno from '@/components/caja/CierreTurno';
import ResumenTurno from '@/components/caja/ResumenTurno';
import { Icon, PageHeader } from '@/components/ui';
import { useSesion } from '@/lib/auth';
import { formatearFechaHora } from '@/lib/caja';
import { mensajeDeError } from '@/lib/http';
import { notificar } from '@/lib/notificaciones';
import { useConsultaApi } from '@/lib/useConsultaApi';
import { useTitulo } from '@/lib/useTitulo';
import type { TurnoCaja } from '@/types/turno';

export default function TurnoDetalle() {
    useTitulo('Detalle del turno');
    const { turnoId } = useParams();
    const { permisos } = useSesion();
    const consulta = useConsultaApi<{ data: TurnoCaja; denominaciones: number[] }>('/turnos/'+turnoId);
    const [cerrando, setCerrando] = useState(false);
    const volver = <Link to="/turnos" className="btn btn-outline-secondary"><Icon name="arrow-left" className="me-2" />Volver a Turnos</Link>;

    if (consulta.error) {
        const noDisponible = axios.isAxiosError(consulta.error) && consulta.error.response?.status === 404;

        return <>
            <PageHeader title="Detalle del turno" actions={volver} />
            <div role="alert">
                <p>{noDisponible ? 'El turno no existe o ya no está disponible.' : 'No se pudo cargar el turno. '+mensajeDeError(consulta.error)}</p>
                {!noDisponible && <Button variant="outline-secondary" onClick={consulta.recargar}>Reintentar</Button>}
            </div>
        </>;
    }

    if (!consulta.datos) {
        return <p role="status"><Spinner animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando turno…</p>;
    }

    const turno = consulta.datos.data;
    const puedeCerrar = turno.cerrada_at === null && permisos.includes('gestionar_cajas');

    return <>
        <PageHeader title={(turno.caja?.nombre ?? 'Caja')+' · Turno '+turno.numero}
            subtitle={'Abierto por '+turno.usuario+' el '+formatearFechaHora(turno.abierta_at)+(turno.cerrada_at ? ' · cerrado el '+formatearFechaHora(turno.cerrada_at)+(turno.cerrado_por ? ' por '+turno.cerrado_por : '') : ' · sigue abierto')}
            actions={<div className="d-flex flex-wrap gap-2">{volver}{puedeCerrar && <Button variant="danger" onClick={() => setCerrando(true)}><Icon name="lock" className="me-2" />Cerrar turno</Button>}</div>} />
        <ResumenTurno turno={turno} />
        {cerrando && <CierreTurno ruta={'/turnos/'+turno.id+'/cerrar'} titulo={'Cerrar turno '+turno.numero} denominaciones={consulta.datos.denominaciones} onHide={() => setCerrando(false)}
            onCerrado={() => { setCerrando(false); notificar('Turno cerrado correctamente.'); consulta.recargar(); }} />}
    </>;
}

