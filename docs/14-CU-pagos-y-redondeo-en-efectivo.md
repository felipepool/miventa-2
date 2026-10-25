# 14-pagos-y-redondeo-en-efectivo

## Caso de uso: Cobrar una venta con uno o varios medios de pago

### Objetivo
Permitir cobrar con efectivo, débito, crédito o transferencia, solos o combinados, con el redondeo legal del efectivo y el cálculo del vuelto.

### Actores
- Usuario autenticado con permiso `vender` que tiene un turno de caja abierto.

### Precondiciones
- La venta tiene al menos un producto y un turno abierto (ver `14-CU-registrar-venta-de-mostrador`).

### Flujo principal
1. El vendedor elige los medios de pago e indica los montos de los que no son efectivo.
2. El cliente envía `pagos`, por ejemplo `[{ "medio": "debito", "monto": 2000 }, { "medio": "efectivo", "recibido": 5000 }]`.
3. El servidor suma los pagos sin efectivo y calcula lo que falta cobrar en efectivo.
4. Redondea esa parte al múltiplo de $10 más cercano, calcula el vuelto con lo recibido y fija el total de la venta.
5. Guarda un pago por medio y el redondeo en la venta.

### Flujos alternativos y errores
- Medio desconocido, sin monto (en medios distintos de efectivo), monto decimal o negativo: `422`.
- Un medio repetido o dos pagos en efectivo: `422` en `pagos`.
- Los pagos sin efectivo no cubren el total, o lo superan: `422` en `pagos`.
- Efectivo recibido menor a lo que corresponde cobrar: `422` en `pagos`.
- Pago en efectivo cuando el total ya está cubierto por otros medios: `422` en `pagos`.
- Venta con pagos vacíos: `422`.

### Reglas de negocio
- Solo el efectivo se redondea (Ley 20.956): si lo que se cobra en efectivo termina en 1 a 5, se redondea hacia abajo; de 6 a 9, hacia arriba. Débito, crédito y transferencia no se redondean.
- El redondeo se aplica solo a la parte pagada en efectivo; el total de la venta es los pagos sin efectivo más el efectivo redondeado.
- El efectivo cubre siempre el resto: el cliente envía el monto de los otros medios y, en efectivo, lo recibido (si se omite, se asume el monto justo).
- El vuelto es lo recibido menos lo cobrado en efectivo y no entra a la caja: el esperado del turno suma solo el efectivo cobrado.
- Cada medio de pago se usa a lo más una vez por venta.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/ventas` | Registra la venta con sus pagos. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "subtotal": 5003,
    "redondeo": -3,
    "total": 5000,
    "pagos": [
      { "medio": "debito", "etiqueta": "Débito", "monto": 2000, "recibido": null, "vuelto": 0 },
      { "medio": "efectivo", "etiqueta": "Efectivo", "monto": 3000, "recibido": 5000, "vuelto": 2000 }
    ]
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los pagos no cubren el total de la venta.",
  "errors": { "pagos": ["Los pagos no cubren el total de la venta."] }
}
```

### Tablas y columnas
- `pagos`: `venta_id`, `medio`, `monto`, `recibido`, `vuelto`, `created_at`.
- `ventas`: `redondeo`, `total`.

### Consideraciones de seguridad
- El total, el redondeo y el vuelto los calcula el servidor con aritmética entera; el cliente no puede fijar el total.
- Los montos son enteros en pesos, sin `float`, y están acotados.
