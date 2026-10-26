# 15-reintentar-devolucion

## Caso de uso: Reintentar una devolución sin duplicarla

### Objetivo
Reconocer una solicitud ya registrada y rechazar una clave reutilizada con otra operación.

### Actores
- Usuario autorizado para devolver productos.

### Precondiciones
- Solicitud válida con clave de idempotencia y contexto autorizado de negocio y sucursal.

### Flujo principal
1. El cliente envía la devolución con `clave_idempotencia`.
2. El servidor busca la clave dentro del negocio, coordinando las solicitudes concurrentes.
3. Si existe, compara venta, usuario, medio, motivo, reintegro de stock y líneas con sus cantidades.
4. Si coincide, responde 200 sin volver a mover dinero ni stock; si difiere, responde 409.

### Reglas de negocio
- La clave es única por negocio, también entre sucursales y cajas diferentes.
- El orden de las líneas y formatos numéricos equivalentes no cambian la solicitud.
- Una clave perteneciente a otro negocio no produce conflicto ni revela su existencia.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/ventas/{venta}/devoluciones` | Registrar o reconocer el reintento. |

### Respuesta de éxito (200, extracto)
```json
{"data":{"estado":"pagada","devolucion":"parcial","total_devuelto":1000}}
```

### Respuesta de error (409)
```json
{"message":"Esta clave de idempotencia ya se utilizó para otra solicitud."}
```

### Tablas y columnas
- `devoluciones`: `negocio_id`, `clave_idempotencia`, `venta_id`, `user_id`, `medio`, `motivo`, `reintegra_stock`.
- `devolucion_items`: `venta_item_id`, `cantidad`.

### Consideraciones de seguridad
- El conflicto no revela la venta ni el usuario de la operación anterior.
- La comprobación no desactiva permisos, aislamiento entre negocios ni validación del servidor.
