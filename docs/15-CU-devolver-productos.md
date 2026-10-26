# 15-devolver-productos

## Caso de uso: Devolver productos de una venta

### Objetivo
Registrar devoluciones parciales o totales sin superar las cantidades vendidas ni el saldo cobrado.

### Actores
- Usuario con permiso `anular_ventas`.

### Precondiciones
- Negocio habilitado para escritura, sucursal seleccionada y turno propio abierto.
- Venta pagada de la misma sucursal con productos pendientes de devolución.

### Flujo principal
1. El usuario abre Devolver; `EditDialog` consulta nuevamente la venta.
2. Se monta `FormDialog` con cantidades disponibles, medio, motivo y opción de reintegrar stock.
3. El servidor valida permisos y cantidades y bloquea el turno y la venta.
4. Calcula el importe proporcional, limitado al total cobrado menos todos los reembolsos anteriores. En efectivo conserva múltiplos de $10 sin superar el saldo.
5. Registra la devolución y, si corresponde, reintegra stock. Responde con la venta actualizada.

### Reglas de negocio
- Ningún medio de reembolso puede superar el saldo cobrado, incluido el redondeo original.
- Los montos de las líneas de devolución suman exactamente el dinero reembolsado, después del redondeo o del límite por saldo.
- El último reembolso de una línea consume su importe proporcional pendiente, sujeto al saldo de la venta.
- Solo el efectivo reduce el esperado de caja; requiere efectivo disponible.
- Reintegrar stock es opcional y requiere el adicional Inventario y un descuento de stock original.
- El diálogo informa carga, error con reintento y venta no disponible; impide envíos duplicados.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/ventas/{venta}` | Datos actuales del formulario. |
| POST | `/api/v1/ventas/{venta}/devoluciones` | Registrar devolución. |

### Respuesta de éxito (201, extracto)
```json
{"data":{"estado":"pagada","devolucion":"total","total_devuelto":2012}}
```

### Respuesta de error (422, extracto)
```json
{"errors":{"items":["Elige al menos un producto con monto a devolver."]}}
```

### Tablas y columnas
- `ventas`: `total`, `estado`, `negocio_id`, `sucursal_id`.
- `venta_items`: `cantidad`, `subtotal`, `descuento`, `descuento_venta`.
- `devoluciones`: `venta_id`, `turno_caja_id`, `user_id`, `medio`, `monto`, `motivo`, `reintegra_stock`.
- `devolucion_items`: `venta_item_id`, `cantidad`, `monto`.
- `stocks`, `movimientos_stock`: reintegros de inventario.

### Consideraciones de seguridad
- El servidor resuelve negocio y sucursal autorizados y calcula el dinero; no acepta montos calculados por el cliente.
- La transacción y los bloqueos impiden superar cantidades o saldos mediante solicitudes concurrentes.
