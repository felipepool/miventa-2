# 10-historial-movimientos-stock

## Caso de uso: Consultar el historial de movimientos de un producto

### Objetivo
Permitir al negocio revisar qué entradas, salidas y ajustes tuvo un producto en la sucursal actual y quién los hizo.

### Actores
- Usuario autenticado con permiso `gestionar_inventario`.

### Precondiciones
- Hay una sucursal activa seleccionada.

### Flujo principal
1. El usuario elige «Ver historial» en un producto del inventario.
2. El cliente envía `GET /api/v1/inventario/{producto}/movimientos?page=`.
3. El servidor devuelve los movimientos del producto en la sucursal actual, del más reciente al más antiguo, paginados de 15 en 15.
4. La pantalla muestra fecha, tipo, cantidad con signo, stock resultante, motivo y usuario.

### Flujos alternativos y errores
- Producto sin movimientos: lista vacía.
- Producto de otro negocio o inexistente: `404`.
- Sin sucursal seleccionada: `409`. Falta de permiso: `403`.

### Reglas de negocio
- El historial es por sucursal: los movimientos de otra sucursal no aparecen.
- Los movimientos no se editan ni se eliminan.
- La cantidad es la variación con signo (negativa en las salidas y en los ajustes que bajan el stock).

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/inventario/{producto}/movimientos?page=` | Historial paginado del producto en la sucursal actual. |

### Respuesta de éxito (200)
```json
{
  "data": [
    {
      "id": "01J...",
      "tipo": "entrada",
      "cantidad": "2.500",
      "stock_resultante": "12.500",
      "motivo": "Compra",
      "usuario": "Administrador",
      "created_at": "2026-10-25T18:30:00-03:00"
    }
  ]
}
```

### Respuesta de error (404)
```json
{
  "message": "No query results for model [App\\Models\\Producto]."
}
```

### Tablas y columnas
- `movimientos_stock`: `public_id`, `negocio_id`, `sucursal_id`, `producto_id`, `user_id`, `tipo`, `cantidad`, `stock_resultante`, `motivo`, `created_at`.

### Consideraciones de seguridad
- El historial se limita al negocio y a la sucursal actuales mediante los filtros del modelo, y el producto de otro negocio responde `404`.
- Solo se expone el nombre del usuario que registró el movimiento, nunca identificadores internos.
