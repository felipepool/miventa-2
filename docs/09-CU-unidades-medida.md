# 09-unidades-medida

## Caso de uso: Medir un producto por unidad, peso, volumen o largo

### Objetivo
Permitir que el negocio registre productos que se miden por unidad, peso, litro o metro, como alimentos a granel, bebidas, insumos o cables.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Hay una sucursal activa seleccionada y el negocio permite escritura.

### Flujo principal
1. El usuario elige la «Unidad de medida» al crear o editar un producto.
2. El cliente envía `unidad_venta` con uno de los valores permitidos.
3. El servidor valida el valor y guarda el producto; el precio y el costo se expresan por esa unidad.

### Flujos alternativos y errores
- Unidad no permitida: `422` en `unidad_venta`.

### Reglas de negocio
- Unidades permitidas: `unidad`, `kg`, `g`, `l`, `ml` y `m`.
- El módulo «Venta por medida» (antes «Venta por peso») describe la venta por kilo, litro o metro en el plan Comercio.
- No hay conversión automática entre unidades; las recetas y el stock en unidad base quedan fuera de este alcance.
- La columna conserva el nombre `unidad_venta`.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/productos` | Crear producto con su unidad de medida. |
| PUT | `/api/v1/productos/{id}` | Cambiar la unidad de medida. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "id": "01J...",
    "nombre": "Cable eléctrico",
    "unidad_venta": "m",
    "precio": 850
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "unidad_venta": ["El campo unidad de medida seleccionado no es válido."]
  }
}
```

### Tablas y columnas
- `productos`: `unidad_venta` (string de hasta 10 caracteres).

### Consideraciones de seguridad
- La unidad se valida en el servidor contra el enum `UnidadVenta`; el cliente no puede enviar valores arbitrarios.
