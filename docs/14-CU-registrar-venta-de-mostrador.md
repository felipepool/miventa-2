# 14-registrar-venta-de-mostrador

## Caso de uso: Registrar una venta de mostrador

### Objetivo
Permitir al vendedor registrar una venta pagada al momento, con sus productos, modificadores, descuentos y pagos, dejando todo calculado y guardado por el servidor.

### Actores
- Usuario autenticado con permiso `vender` que tiene un turno de caja abierto.

### Precondiciones
- Hay una sucursal activa seleccionada, el negocio permite escritura y el usuario tiene su turno de caja abierto.

### Flujo principal
1. El vendedor abre Vender y la pantalla carga el catálogo con `GET /api/v1/pos`.
2. Agrega productos al carrito, elige modificadores cuando el producto los tiene y define cantidades.
3. Elige cómo paga el cliente y confirma.
4. El cliente envía `POST /api/v1/ventas` con `{ "items": [{ "producto": "01J...", "cantidad": 2, "modificadores": [{ "id": "01J...", "cantidad": 1 }] }], "pagos": [{ "medio": "efectivo", "recibido": 5000 }], "clave_idempotencia": "..." }`.
5. El servidor bloquea el negocio y el turno, recalcula líneas, modificadores, descuentos y cobro, asigna los folios y guarda la venta con sus líneas y pagos.
6. Con el adicional Inventario activo, descuenta el stock de la sucursal; el efectivo cobrado suma al esperado del turno.
7. Responde con la venta (folio, totales, pagos y vuelto) y la pantalla muestra la confirmación.

### Flujos alternativos y errores
- Sin turno abierto, o con el turno ya cerrado: `422` en `turno`.
- Sin productos, producto desconocido, de otro negocio, inactivo, que solo es insumo o sin precio: `422` en `items`.
- Cantidad cero, negativa, con más de 3 decimales, con decimales en un producto por unidad o mayor a 99.999: `422` en `items.N.cantidad`.
- Modificador desconocido, inactivo, ajeno al producto o que incumple las reglas de su grupo: `422` en `items.N.modificadores`.
- Pagos que no cubren el total o lo superan: `422` en `pagos`.
- Falta de permiso: `403`. Negocio en solo lectura: `403`. Sin sucursal seleccionada: `409`.
- Repetir el envío con la misma clave de idempotencia devuelve la venta ya registrada (`200`) sin duplicarla.

### Reglas de negocio
- Los montos son pesos enteros con IVA incluido; el IVA no se vuelve a aplicar.
- Solo se venden productos activos, a la venta y con precio. El precio de la línea es el vigente al vender y queda copiado en la venta junto con el nombre, el SKU y el costo.
- Subtotal de línea = cantidad × (precio + modificadores por unidad), redondeado al peso.
- Los productos por unidad se venden en cantidades enteras; los medidos por peso, volumen o largo admiten hasta 3 decimales.
- El folio de la venta es correlativo por sucursal; además cada venta tiene un número correlativo por negocio.
- La venta es atómica: si algo falla no queda nada guardado.
- Con el adicional Inventario activo, el stock de los productos con control de stock baja en la sucursal y puede quedar negativo; sin el adicional no se toca. Queda un movimiento de tipo `venta` ligado a la venta.
- Anular, devolver y compartir comprobantes quedan fuera de este caso de uso.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/ventas` | Registrar una venta pagada. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "id": "01J...",
    "folio": 12,
    "numero": 35,
    "estado": "pagada",
    "subtotal": 3000,
    "descuento": 0,
    "redondeo": 0,
    "total": 3000,
    "pagada_at": "2026-10-25T18:00:00-03:00",
    "vendedor": "María Pérez",
    "pagos": [{ "medio": "efectivo", "etiqueta": "Efectivo", "monto": 3000, "recibido": 5000, "vuelto": 2000 }],
    "items": [{ "nombre": "Bebida", "cantidad": "2.000", "precio_unitario": 1500, "subtotal": 3000, "descuento": 0, "total": 3000, "modificadores": [] }]
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Abre tu caja para poder vender.",
  "errors": { "turno": ["Abre tu caja para poder vender."] }
}
```

### Tablas y columnas
- `ventas`: `id`, `public_id`, `negocio_id`, `sucursal_id`, `turno_caja_id`, `user_id`, `numero`, `numero_sucursal`, `estado`, `subtotal`, `descuento`, `descuento_motivo`, `redondeo`, `total`, `notas`, `clave_idempotencia`, `pagada_at`, `created_at`, `updated_at`; folios únicos por negocio y por sucursal, y clave única por negocio.
- `venta_items`: `venta_id`, `producto_id`, `nombre`, `sku`, `unidad_venta`, `cantidad`, `precio_unitario`, `costo_unitario`, `modificadores_unitario`, `subtotal`, `descuento`, `descuento_venta`, `notas`, `orden`.
- `venta_item_modificadores`: `venta_item_id`, `modificador_id`, `grupo`, `nombre`, `cantidad`, `precio_adicional`, `total`.
- `pagos`: `venta_id`, `medio`, `monto`, `recibido`, `vuelto`, `created_at`.
- `movimientos_stock`: `venta_id` (nuevo) y tipo `venta`.

### Consideraciones de seguridad
- El servidor recalcula todo; el cliente solo envía productos, cantidades, modificadores, descuentos y pagos, nunca precios, totales, folios, negocio, sucursal, turno ni vendedor.
- Los productos y modificadores se resuelven dentro del negocio autenticado; los de otro negocio se tratan como inexistentes.
- El negocio y el turno se bloquean durante el registro: los folios no se repiten, la venta queda en orden con el cierre del turno y la clave de idempotencia evita duplicados por envíos repetidos o simultáneos.
- El permiso `vender` se comprueba en la ruta y de nuevo dentro de la operación.
