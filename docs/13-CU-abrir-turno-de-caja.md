# 13-abrir-turno-de-caja

## Caso de uso: Abrir un turno de caja

### Objetivo
Permitir a quien opera la caja abrir su turno con un monto inicial en una caja activa de la sucursal en la que trabaja.

### Actores
- Usuario autenticado con permiso `operar_caja`.

### Precondiciones
- Hay una sucursal activa seleccionada y el negocio permite escritura.
- La sucursal tiene al menos una caja activa sin turno abierto.

### Flujo principal
1. El usuario abre el punto de venta desde el menú principal (pestaña nueva) y entra a Mi caja.
2. El cliente consulta `GET /api/v1/caja` y recibe su turno abierto, si lo hay, y las cajas de la sucursal con quién las ocupa.
3. Sin turno abierto, el usuario elige una caja libre, escribe el monto inicial y una nota opcional.
4. El cliente envía `POST /api/v1/caja/abrir` con `{ "caja": "01J...", "monto_inicial": 20000, "nota": "Sencillo" }`.
5. El servidor bloquea la caja, valida las reglas y crea el turno con el siguiente número de esa caja.

### Flujos alternativos y errores
- Caja inexistente, de otra sucursal o de otro negocio: `422` en `caja`.
- Caja desactivada: `422` en `caja`. Caja con turno abierto de otra persona: `422` en `caja`.
- El usuario ya tiene un turno abierto: `422` en `caja`.
- Monto inicial negativo, decimal o superior a 99.999.999: `422` en `monto_inicial`.
- Falta de permiso: `403`. Negocio en solo lectura: `403`. Sin sucursal seleccionada: `409`.

### Reglas de negocio
- Una caja tiene a lo más un turno abierto y una persona solo uno abierto a la vez.
- El número del turno es correlativo por caja.
- El monto inicial es un monto entero en pesos.
- El turno pertenece a quien lo abrió y solo esa persona registra movimientos en él.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/caja` | Mi turno abierto y las cajas de la sucursal actual. |
| POST | `/api/v1/caja/abrir` | Abrir un turno en una caja libre. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "id": "01J...",
    "numero": 12,
    "caja": { "id": "01J...", "nombre": "Caja 1" },
    "monto_inicial": 20000,
    "nota_apertura": "Sencillo",
    "abierta_at": "2026-10-25T09:00:00-03:00",
    "cerrada_at": null
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": { "caja": ["Esta caja ya está abierta por otra persona."] }
}
```

### Tablas y columnas
- `turnos_caja`: `id`, `public_id`, `negocio_id`, `sucursal_id`, `caja_id`, `user_id`, `numero`, `monto_inicial`, `nota_apertura`, `abierta_at`, `cerrada_at`, `cerrada_por_id`, `nota_cierre`, `monto_esperado`, `monto_contado`, `diferencia`, `conteo`, `created_at`, `updated_at`; número único por caja.

### Consideraciones de seguridad
- La caja se resuelve dentro de la sucursal actual del negocio autenticado; el cliente no fija `negocio_id`, `sucursal_id` ni `user_id`.
- La caja se bloquea durante la apertura y el número es único por caja, para que dos aperturas simultáneas no dejen dos turnos abiertos.
- El permiso `operar_caja` se comprueba en la ruta y de nuevo dentro de la operación.
