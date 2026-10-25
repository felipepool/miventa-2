# 13-historial-de-turnos

## Caso de uso: Consultar el historial de turnos y su comprobante

### Objetivo
Permitir revisar los turnos de caja y el detalle de cada cierre, para controlar el efectivo del negocio.

### Actores
- Usuario autenticado con permiso `operar_caja`: ve sus propios turnos.
- Usuario con permiso `gestionar_cajas`: ve todos los turnos de la sucursal.

### Precondiciones
- Hay una sucursal activa seleccionada.

### Flujo principal
1. El usuario abre Turnos en el punto de venta.
2. El cliente envía `GET /api/v1/turnos` con filtros opcionales `caja`, `usuario`, `estado` (`abierto` o `cerrado`), `desde`, `hasta` y `page`.
3. El servidor devuelve los turnos de la sucursal actual, del más reciente al más antiguo, paginados de 15 en 15.
4. El usuario abre un turno con `GET /api/v1/turnos/{turno}` y ve su resumen, los movimientos y, si ya cerró, el conteo y el comprobante.

### Flujos alternativos y errores
- Estado o fecha inválidos, o `hasta` anterior a `desde`: `422`.
- Turno de otra sucursal, de otro negocio, o ajeno para quien no tiene `gestionar_cajas`: `404`.
- Sin sucursal seleccionada: `409`. Falta de permiso: `403`.

### Reglas de negocio
- Un cajero ve solo los turnos que abrió; quien tiene `gestionar_cajas` ve todos los de la sucursal actual.
- Con cierre ciego, quien no tiene `gestionar_cajas` no recibe el esperado ni la diferencia, tampoco en el historial.
- Los turnos no se eliminan.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/turnos?caja=&usuario=&estado=&desde=&hasta=&page=` | Listar y filtrar turnos. |
| GET | `/api/v1/turnos/{turno}` | Detalle de un turno con sus movimientos. |

### Respuesta de éxito (200)
```json
{
  "data": {
    "id": "01J...",
    "numero": 12,
    "caja": { "id": "01J...", "nombre": "Caja 1" },
    "usuario": "María Pérez",
    "monto_inicial": 20000,
    "ingresos": 10000,
    "retiros": 5000,
    "monto_esperado": 25000,
    "monto_contado": 25500,
    "diferencia": 500,
    "abierta_at": "2026-10-25T09:00:00-03:00",
    "cerrada_at": "2026-10-25T18:00:00-03:00",
    "movimientos": [{ "id": "01J...", "tipo": "retiro", "monto": 5000, "motivo": "Pago proveedor de pan" }]
  }
}
```

### Respuesta de error (404)
```json
{
  "message": "No query results for model [App\\Models\\TurnoCaja]."
}
```

### Tablas y columnas
- `turnos_caja` y `movimientos_caja`: ver los casos de uso de apertura y movimientos.

### Consideraciones de seguridad
- Los turnos se limitan al negocio y a la sucursal actuales con los filtros del modelo; los identificadores internos no se exponen.
- La visibilidad por usuario se aplica en el servidor, no en la pantalla.
- El esperado y la diferencia se omiten de la respuesta cuando el cierre es ciego y el usuario no tiene `gestionar_cajas`.
