# 13-movimientos-de-caja

## Caso de uso: Registrar ingresos y retiros de efectivo

### Objetivo
Permitir registrar las entradas y salidas de efectivo de la caja que no son ventas, con su motivo, para que el cierre cuadre.

### Actores
- Usuario autenticado con permiso `operar_caja` que tiene el turno abierto.

### Precondiciones
- El usuario tiene un turno abierto en la sucursal actual y el negocio permite escritura.

### Flujo principal
1. En Mi caja, el usuario elige «Ingreso» o «Retiro».
2. El cliente envía `POST /api/v1/caja/movimientos` con `{ "tipo": "retiro", "monto": 5000, "motivo": "Pago proveedor de pan" }`.
3. El servidor bloquea el turno, valida las reglas y registra el movimiento.
4. La pantalla actualiza el resumen del turno y la lista de movimientos.

### Flujos alternativos y errores
- Sin turno abierto: `422` en `monto`.
- Tipo desconocido, monto cero, negativo, decimal o superior a 99.999.999: `422`.
- Motivo vacío o de más de 150 caracteres: `422` en `motivo`.
- Retiro mayor al efectivo disponible: `422` en `monto`; con cierre ciego el mensaje no revela el saldo.
- Falta de permiso: `403`. Negocio en solo lectura: `403`.

### Reglas de negocio
- `ingreso` suma efectivo a la caja y `retiro` lo resta; el motivo es obligatorio en ambos.
- Un retiro no puede superar el efectivo disponible (monto inicial más ingresos y ventas en efectivo, menos retiros).
- Solo quien abrió el turno registra movimientos; los movimientos de un turno cerrado no se agregan ni se modifican.
- Los movimientos no se editan ni se eliminan: un error se corrige con otro movimiento.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/caja/movimientos` | Registrar un ingreso o un retiro en mi turno abierto. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "id": "01J...",
    "tipo": "retiro",
    "monto": 5000,
    "motivo": "Pago proveedor de pan",
    "usuario": "María Pérez",
    "created_at": "2026-10-25T11:20:00-03:00"
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": { "monto": ["No hay suficiente efectivo en la caja para este retiro."] }
}
```

### Tablas y columnas
- `movimientos_caja`: `id`, `public_id`, `turno_caja_id`, `user_id`, `tipo`, `monto`, `motivo`, `created_at`.

### Consideraciones de seguridad
- El turno se obtiene del usuario autenticado; el cliente no envía el identificador del turno ni el usuario.
- El turno se bloquea durante el registro para que retiros simultáneos no superen el efectivo disponible.
- Los montos son enteros en pesos, sin `float`.
