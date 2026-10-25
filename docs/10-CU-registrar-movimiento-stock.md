# 10-registrar-movimiento-stock

## Caso de uso: Registrar un movimiento de stock

### Objetivo
Permitir al negocio registrar entradas, salidas y ajustes de inventario de un producto en la sucursal actual, dejando constancia de quién, cuándo y por qué.

### Actores
- Usuario autenticado con permiso `gestionar_inventario`.

### Precondiciones
- Hay una sucursal activa seleccionada, el negocio permite escritura, el producto controla stock y el adicional Inventario está activo (ver `11-CU-acceso-inventario-contratado`).

### Flujo principal
1. El usuario elige «Movimiento» en un producto del inventario.
2. El cliente envía `POST /api/v1/inventario/{producto}/movimientos` con `{ "tipo": "entrada", "cantidad": 5, "motivo": "Compra" }`.
3. El servidor valida la entrada y bloquea el stock del producto en la sucursal dentro de una transacción; si el stock aún no existe, lo crea.
4. Calcula el nuevo stock, lo guarda y registra el movimiento con su variación con signo y el stock resultante.
5. Responde con el movimiento; la pantalla muestra el stock actualizado.

### Flujos alternativos y errores
- Tipo desconocido, cantidad vacía, no numérica, en arreglo o sobre el máximo: `422`.
- Cantidad cero o negativa en entrada o salida, o negativa en un ajuste: `422` en `cantidad`.
- Decimales en un producto medido por `unidad`, o más de 3 decimales en los demás: `422`.
- Salida mayor al stock disponible: `422` en `cantidad`, sin cambios.
- Salida o ajuste sin motivo: `422` en `motivo`.
- Producto sin control de stock: `422` en `producto`. Producto de otro negocio: `404`.
- Falta de permiso: `403`. Negocio en solo lectura: `403`.

### Reglas de negocio
- `entrada` suma la cantidad; `salida` la resta; `ajuste` fija el stock en la cantidad indicada (el stock real contado).
- No se permite stock negativo.
- El motivo es obligatorio en salidas y ajustes, opcional en entradas, de hasta 200 caracteres.
- Los movimientos son definitivos: no se editan ni se eliminan.
- Dos movimientos simultáneos sobre el mismo producto se aplican en orden; si el segundo deja el stock negativo, se rechaza.
- El stock inicial se carga con un ajuste.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/inventario/{producto}/movimientos` | Registrar una entrada, salida o ajuste. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "id": "01J...",
    "tipo": "salida",
    "cantidad": "-4.000",
    "stock_resultante": "8.500",
    "motivo": "Merma",
    "usuario": "Administrador",
    "created_at": "2026-10-25T18:30:00-03:00"
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "cantidad": ["No hay stock suficiente. Disponible: 3."]
  }
}
```

### Tablas y columnas
- `stocks`: `cantidad`.
- `movimientos_stock`: `id`, `public_id`, `negocio_id`, `sucursal_id`, `producto_id`, `user_id`, `tipo`, `cantidad` (variación con signo), `stock_resultante`, `motivo`, `created_at`.

### Consideraciones de seguridad
- El negocio, la sucursal, el usuario y el stock resultante los fija el servidor; el cliente no puede enviarlos.
- Bloqueo de la fila de stock y transacción para evitar condiciones de carrera; restricción única por sucursal y producto.
- Las cantidades se calculan con aritmética decimal exacta, sin `float`.
- El permiso `gestionar_inventario` se comprueba en la ruta y de nuevo dentro de la operación.

