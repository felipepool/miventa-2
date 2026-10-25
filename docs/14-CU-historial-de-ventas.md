# 14-historial-de-ventas

## Caso de uso: Consultar el historial de ventas y su detalle

### Objetivo
Permitir revisar las ventas registradas en la sucursal, con sus líneas, modificadores, descuentos y pagos.

### Actores
- Usuario autenticado con permiso `vender`: ve sus propias ventas.
- Usuario con permiso `gestionar_cajas`: ve todas las ventas de la sucursal.

### Precondiciones
- Hay una sucursal activa seleccionada.

### Flujo principal
1. El usuario abre Historial en el punto de venta.
2. El cliente envía `GET /api/v1/ventas` con filtros opcionales `folio`, `usuario`, `medio`, `desde`, `hasta` y `page`.
3. El servidor devuelve las ventas de la sucursal actual, de la más reciente a la más antigua, paginadas de 15 en 15, con sus medios de pago.
4. El usuario abre una venta con `GET /api/v1/ventas/{venta}` y ve sus líneas, modificadores, descuentos y pagos.

### Flujos alternativos y errores
- Medio, fecha o folio inválidos, o `hasta` anterior a `desde`: `422`.
- Venta de otra sucursal, de otro negocio o de otra persona para quien no tiene `gestionar_cajas`: `404`.
- Sin sucursal seleccionada: `409`. Falta de permiso: `403`.

### Reglas de negocio
- El vendedor ve solo las ventas que registró; quien tiene `gestionar_cajas` ve todas las de la sucursal actual y puede filtrar por vendedor.
- El filtro por medio muestra las ventas que tienen al menos un pago con ese medio.
- Las ventas no se eliminan ni se editan.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/ventas?folio=&usuario=&medio=&desde=&hasta=&page=` | Listar y filtrar ventas. |
| GET | `/api/v1/ventas/{venta}` | Detalle de una venta. |

### Respuesta de éxito (200)
```json
{
  "data": [{
    "id": "01J...", "folio": 12, "numero": 35, "estado": "pagada", "total": 3000, "pagada_at": "2026-10-25T18:00:00-03:00",
    "vendedor": "María Pérez", "items_count": 2,
    "pagos": [{ "medio": "debito", "etiqueta": "Débito", "monto": 3000, "recibido": null, "vuelto": 0 }]
  }],
  "usuarios": [{ "id": "01J...", "name": "María Pérez" }],
  "medios_pago": [{ "value": "efectivo", "label": "Efectivo" }]
}
```

### Respuesta de error (404)
```json
{
  "message": "No query results for model [App\\Models\\Venta]."
}
```

### Tablas y columnas
- `ventas`, `venta_items`, `venta_item_modificadores` y `pagos`: solo lectura.

### Consideraciones de seguridad
- Las ventas se limitan al negocio y a la sucursal actuales con los filtros del modelo; los identificadores internos no se exponen.
- La visibilidad por vendedor se aplica en el servidor, no en la pantalla.
