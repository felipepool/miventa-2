# 09-tipo-producto

## Caso de uso: Clasificar un producto como de venta, insumo o ambos

### Objetivo
Distinguir los productos que se venden de los insumos que se compran para preparar otros productos, sin exigir precio de venta a los insumos.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Hay una sucursal activa seleccionada y el negocio permite escritura.

### Flujo principal
1. El usuario crea o edita un producto y marca «Se vende», «Es insumo» o ambos.
2. El cliente envía `POST /api/v1/productos` o `PUT /api/v1/productos/{id}` con `se_vende` y `es_insumo`.
3. El servidor valida que al menos uno sea verdadero y que el precio exista si el producto se vende.
4. El listado muestra el tipo de cada producto y permite filtrar con `tipo=venta` o `tipo=insumo`.

### Flujos alternativos y errores
- Ni `se_vende` ni `es_insumo` activos: `422` en `se_vende`.
- Producto que se vende sin precio: `422` en `precio`.
- Valor de `tipo` distinto de `venta` o `insumo`: `422`.
- Falta de permiso: `403`. Negocio en solo lectura: `403` al modificar.

### Reglas de negocio
- Un producto puede venderse, ser insumo o ambas cosas.
- El precio es obligatorio solo si el producto se vende; un insumo puede tener solo costo.
- El filtro `tipo=venta` incluye los productos que se venden (también los marcados como insumo) y `tipo=insumo` los insumos (también los que se venden).
- Los productos existentes quedan como «se vende».
- Las recetas y el descuento de stock al vender quedan fuera de este caso de uso.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/productos?tipo=` | Listar productos filtrando por uso. |
| POST | `/api/v1/productos` | Crear producto con su uso. |
| PUT | `/api/v1/productos/{id}` | Editar el uso del producto. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "id": "01J...",
    "nombre": "Palta",
    "se_vende": false,
    "es_insumo": true,
    "unidad_venta": "g",
    "precio": null,
    "costo": 3
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "se_vende": ["Indica si el producto se vende, es un insumo o ambos."]
  }
}
```

### Tablas y columnas
- `productos`: `se_vende` (boolean, por defecto `true`), `es_insumo` (boolean, por defecto `false`) y `precio` (nullable).

### Consideraciones de seguridad
- La validación se hace en el servidor; el cliente no fija `negocio_id`.
- El permiso `gestionar_catalogo` y el aislamiento por negocio se mantienen.
