# 15-anular-venta

## Caso de uso: Anular una venta

### Objetivo
Anular una venta pagada de un turno abierto y revertir su efecto en caja e inventario cuando corresponda.

### Actores
- Usuario con permiso `anular_ventas`.

### Precondiciones
- Negocio habilitado para escritura y sucursal seleccionada.
- Venta pagada, sin devoluciones, cuyo turno sigue abierto.

### Flujo principal
1. El usuario abre Anular; `EditDialog` carga nuevamente la venta.
2. Si todavía admite anulación, `FormDialog` solicita el motivo; en caso contrario se explica por qué no está disponible.
3. El servidor valida autorización y motivo y bloquea turno y venta.
4. Registra la anulación; la venta deja de sumar al esperado y reintegra el stock descontado si Inventario está activo.
5. La pantalla muestra el estado actualizado.

### Reglas de negocio
- Una venta no se anula dos veces ni con turno cerrado o devoluciones anteriores.
- El diálogo comparte carga, error, reintento y registro no disponible con Usuarios.
- El envío se bloquea mientras está en curso.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/ventas/{venta}` | Datos actuales del formulario. |
| POST | `/api/v1/ventas/{venta}/anular` | Anular con motivo. |

### Respuesta de éxito (200, extracto)
```json
{"data":{"estado":"anulada","motivo_anulacion":"Cobro duplicado"}}
```

### Respuesta de error (422, extracto)
```json
{"errors":{"venta":["Esta venta ya está anulada."]}}
```

### Tablas y columnas
- `ventas`: `estado`, `anulada_at`, `anulada_por`, `motivo_anulacion`.
- `turnos_caja`: `cerrada_at`.
- `movimientos_stock`: `venta_id`, `tipo`, `cantidad`.

### Consideraciones de seguridad
- Autorización y aislamiento por negocio y sucursal en servidor.
- La transacción coordina la anulación con el cierre de caja y solicitudes simultáneas.
