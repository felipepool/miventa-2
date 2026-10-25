# 13-cerrar-turno-de-caja

## Caso de uso: Cerrar un turno de caja

### Objetivo
Permitir cerrar el turno contando el efectivo y dejar congelado el esperado, el contado y la diferencia.

### Actores
- Usuario autenticado con permiso `operar_caja` que tiene el turno abierto.
- Usuario con permiso `gestionar_cajas`, que puede cerrar el turno de otra persona.

### Precondiciones
- Existe un turno abierto en la sucursal actual y el negocio permite escritura.

### Flujo principal
1. En Mi caja, el usuario elige «Cerrar caja» e ingresa el conteo por billetes y monedas, o el total contado.
2. El cliente envía `POST /api/v1/caja/cerrar` con `{ "conteo": { "10000": 3, "1000": 7 }, "nota": "..." }` o `{ "monto_contado": 37000 }`.
3. El servidor bloquea el turno, calcula el esperado, registra lo contado y congela la diferencia.
4. La pantalla muestra el comprobante del cierre. Un administrador cierra el turno de otra persona con `POST /api/v1/turnos/{turno}/cerrar`.

### Flujos alternativos y errores
- Turno ya cerrado: `422` en `monto_contado`. Sin turno abierto: `422`.
- Conteo con una denominación que no existe, cantidades negativas, decimales o superiores a 99.999: `422` en `conteo`.
- Ni conteo ni monto contado: `422` en `monto_contado`. Nota de más de 255 caracteres: `422`.
- Cerrar el turno de otra persona sin `gestionar_cajas`: `403`; turno de otra sucursal u otro negocio: `404`.
- Negocio en solo lectura: `403`.

### Reglas de negocio
- Esperado = monto inicial + ingresos − retiros; cuando existan las ventas se suman las ventas en efectivo.
- Diferencia = contado − esperado (positiva es sobrante y negativa, faltante).
- Con la preferencia de cierre ciego activa, el cajero no ve el esperado ni la diferencia, ni antes ni después del cierre; los usuarios con `gestionar_cajas` sí.
- El cierre es definitivo: no se reabre ni se corrige.
- Denominaciones: 20.000, 10.000, 5.000, 2.000, 1.000, 500, 100, 50 y 10.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/caja/cerrar` | Cerrar mi turno abierto. |
| POST | `/api/v1/turnos/{turno}/cerrar` | Cerrar el turno de otra persona (`gestionar_cajas`). |

### Respuesta de éxito (200)
```json
{
  "data": {
    "id": "01J...",
    "numero": 12,
    "cerrada_at": "2026-10-25T18:00:00-03:00",
    "monto_inicial": 20000,
    "monto_contado": 37000,
    "monto_esperado": 36500,
    "diferencia": 500,
    "conteo": [{ "valor": 10000, "cantidad": 3 }, { "valor": 1000, "cantidad": 7 }]
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": { "conteo": ["El conteo tiene billetes o monedas que no existen."] }
}
```

### Tablas y columnas
- `turnos_caja`: `cerrada_at`, `cerrada_por_id`, `nota_cierre`, `monto_esperado`, `monto_contado`, `diferencia`, `conteo`.
- `negocios`: `cierre_ciego`.

### Consideraciones de seguridad
- Las rutas exigen `operar_caja`; cerrar un turno ajeno exige además `gestionar_cajas`, y los permisos se comprueban de nuevo dentro de la operación.
- El esperado y la diferencia se calculan en el servidor; el cliente solo envía lo contado.
- Con cierre ciego la respuesta no incluye el esperado ni la diferencia para quien no tiene `gestionar_cajas`.
- El turno se bloquea durante el cierre para evitar cierres dobles.
