# 10-consultar-inventario

## Caso de uso: Consultar el inventario de la sucursal

### Objetivo
Permitir al negocio ver las existencias de los productos que controlan stock en la sucursal en la que trabaja, y detectar los que están bajo su mínimo.

### Actores
- Usuario autenticado con permiso `gestionar_inventario`.

### Precondiciones
- Hay una sucursal activa seleccionada, el negocio está operativo y el adicional Inventario está activo (en prueba o contratado; ver 11-CU-acceso-inventario-contratado).

### Flujo principal
1. El usuario abre Inventario.
2. El cliente envía `GET /api/v1/inventario` con filtros opcionales `buscar` (nombre, SKU o código de barras), `categoria`, `stock=bajo` y `page`.
3. El servidor devuelve los productos con `controla_stock` activo del negocio, con su cantidad y stock mínimo en la sucursal actual (`0.000` si aún no tienen movimientos).
4. La pantalla muestra la etiqueta «Stock bajo» cuando el mínimo es mayor que cero y la cantidad es igual o menor a él.
5. El usuario define el stock mínimo de un producto con `PUT /api/v1/inventario/{producto}/minimo` y `{ "stock_minimo": 5 }`.

### Flujos alternativos y errores
- Sin sucursal seleccionada: `409` con `code: sucursal_requerida`.
- Filtro `stock` distinto de `bajo`: `422`.
- Stock mínimo negativo, no numérico, con decimales no permitidos o sobre el máximo: `422` en `stock_minimo`.
- Producto de otro negocio o inexistente: `404`. Producto sin control de stock: `422`.
- Adicional Inventario no contratado: `403` con `code: adicional_no_contratado`.
- Falta de permiso: `403`.

### Reglas de negocio
- El stock se lleva por sucursal: lo que ocurre en una no aparece en otra.
- Solo los productos con `controla_stock` activo aparecen en el inventario, estén activos o no.
- El stock mínimo también es por sucursal; en `0` no se usa alerta.
- Las cantidades son decimales de hasta 3 posiciones; los productos medidos por `unidad` usan solo enteros.
- La respuesta incluye las categorías del negocio para el filtro.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/inventario?buscar=&categoria=&stock=&page=` | Listar el inventario de la sucursal actual. |
| PUT | `/api/v1/inventario/{producto}/minimo` | Definir el stock mínimo en la sucursal actual. |

### Respuesta de éxito (200)
```json
{
  "data": [
    {
      "id": "01J...",
      "sku": "PAL-1",
      "codigo_barras": null,
      "nombre": "Palta",
      "unidad_venta": "kg",
      "activo": true,
      "cantidad": "4.500",
      "stock_minimo": "5.000",
      "stock_bajo": true
    }
  ],
  "categorias": [{ "id": "01J...", "nombre": "Verduras", "activa": true }]
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "stock_minimo": ["El campo stock minimo debe ser al menos 0."]
  }
}
```

### Tablas y columnas
- `stocks`: `id`, `negocio_id`, `sucursal_id`, `producto_id`, `cantidad`, `stock_minimo`, `created_at`, `updated_at`; única por `sucursal_id` y `producto_id`.
- `productos`: `controla_stock`, `unidad_venta`.

### Consideraciones de seguridad
- La ruta exige autenticación, `gestionar_inventario` y sucursal seleccionada; los modelos se limitan al negocio y a la sucursal actuales.
- Los identificadores internos no se exponen; solo el ULID público del producto.
- El permiso se vuelve a comprobar dentro de la operación de escritura.

