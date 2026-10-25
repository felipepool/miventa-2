# 14-descuentos-de-venta

## Caso de uso: Aplicar descuentos a una línea o a toda la venta

### Objetivo
Permitir descontar por porcentaje o por monto en una línea o en toda la venta, con motivo y respetando el tope del cajero.

### Actores
- Usuario autenticado con permiso `vender` que tiene un turno de caja abierto.
- Usuario con permiso `descontar_sin_limite`, que puede superar el tope.

### Precondiciones
- La venta tiene al menos un producto y un turno abierto.

### Flujo principal
1. El vendedor aplica un descuento a una línea (`items.N.descuento`) o a toda la venta (`descuento`), y escribe el motivo.
2. El cliente envía `{ "tipo": "porcentaje", "valor": 10 }` o `{ "tipo": "monto", "valor": 500 }` junto con `motivo_descuento`.
3. El servidor calcula primero los descuentos de línea y luego el de la venta sobre lo que queda, repartiéndolo entre las líneas en proporción a su valor.
4. Comprueba el tope del cajero y guarda el descuento total y su motivo en la venta.

### Flujos alternativos y errores
- Tipo distinto de `porcentaje` o `monto`, valor cero, decimal o ausente: `422`.
- Porcentaje fuera de 1 a 100, o monto mayor a lo que descuenta: `422`.
- Un descuento que cubre toda la venta: `422` en `descuento`.
- Descuento sin motivo: `422` en `motivo_descuento`.
- Descuento sobre el tope del cajero sin el permiso `descontar_sin_limite`: `422` en `descuento`.

### Reglas de negocio
- El porcentaje se redondea al peso más cercano (la mitad hacia arriba) y el descuento de la venta se reparte sin dejar pesos sueltos: las partes suman exactamente el descuento.
- El tope es la preferencia `descuento_maximo_cajero` del negocio (porcentaje sobre el subtotal de la venta, contando los descuentos de las líneas y el de la venta).
- El motivo es obligatorio cuando hay descuento y se guarda una sola vez en la venta.
- La venta debe quedar con un total mayor que cero.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/ventas` | Registra la venta con sus descuentos. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "subtotal": 6666,
    "descuento": 1334,
    "descuento_motivo": "Cliente frecuente",
    "total": 5332,
    "items": [{ "subtotal": 3333, "descuento": 667, "total": 2666 }, { "subtotal": 3333, "descuento": 667, "total": 2666 }]
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Puedes descontar hasta un 10% de la venta. Pide a un administrador un descuento mayor.",
  "errors": { "descuento": ["Puedes descontar hasta un 10% de la venta. Pide a un administrador un descuento mayor."] }
}
```

### Tablas y columnas
- `ventas`: `descuento`, `descuento_motivo`.
- `venta_items`: `descuento` (de la línea) y `descuento_venta` (parte repartida del descuento de la venta).
- `negocios`: `descuento_maximo_cajero`.

### Consideraciones de seguridad
- El tope y el permiso se evalúan en el servidor con el usuario autenticado, no con datos de la pantalla.
- El descuento se calcula con aritmética entera; la pantalla solo lo muestra.
