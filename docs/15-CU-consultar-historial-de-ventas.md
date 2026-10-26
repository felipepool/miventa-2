# 15-consultar-historial-de-ventas

## Caso de uso: Consultar ventas anuladas y con devolución

### Objetivo
Permitir distinguir y filtrar ventas pagadas, anuladas y con devoluciones en la sucursal seleccionada.

### Actores
- Usuario autorizado para consultar ventas de la sucursal.

### Precondiciones
- Sesión autenticada y sucursal seleccionada.

### Flujo principal
1. El usuario abre el historial de ventas y selecciona un estado, si desea filtrar.
2. El servidor valida el filtro y limita los resultados a las ventas que el usuario puede consultar.
3. El historial muestra la etiqueta de estado y el detalle permite consultar la anulación o las devoluciones y sus líneas.

### Reglas de negocio
- Una venta con devoluciones conserva el estado `pagada` y se presenta como «Con devolución» o «Devuelta», según las cantidades devueltas.
- El filtro distingue pagadas sin devolución, anuladas y ventas con devolución.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/ventas?estado=pagada` | Filtrar ventas pagadas sin devolución. |
| GET | `/api/v1/ventas?estado=anulada` | Filtrar ventas anuladas. |
| GET | `/api/v1/ventas?estado=con_devolucion` | Filtrar ventas con devolución. |
| GET | `/api/v1/ventas/{venta}` | Consultar anulación y devoluciones de una venta. |

### Respuesta de éxito (200, extracto)
```json
{"data":[{"estado":"pagada","devolucion":"parcial","total_devuelto":1000}]}
```

### Respuesta de error (422, extracto)
```json
{"errors":{"estado":["El valor seleccionado para estado no es válido."]}}
```

### Tablas y columnas
- `ventas`: `estado`, `sucursal_id`, `negocio_id`, `anulada_at`, `motivo_anulacion`.
- `devoluciones`: `venta_id`, `monto`, `medio`.
- `devolucion_items`: `venta_item_id`, `cantidad`, `monto`.

### Consideraciones de seguridad
- El servidor aplica el aislamiento por negocio y sucursal y las reglas existentes sobre qué ventas puede consultar cada usuario.
