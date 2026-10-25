# 08-categoria-opcional-productos

## Caso de uso: Crear y editar productos sin categoría

### Objetivo
Permitir al negocio registrar productos sin asignarles una categoría, de modo que no necesite crear categorías para empezar a usar el catálogo.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Hay una sucursal activa seleccionada y el negocio permite escritura.

### Flujo principal
1. El usuario abre Productos; aunque no existan categorías, la pantalla ofrece «Nuevo producto».
2. El usuario completa el formulario y deja la categoría en «Sin categoría» (o elige una categoría activa).
3. El cliente envía `POST /api/v1/productos` con `"categoria": null` (o sin el campo); en edición usa `PUT /api/v1/productos/{id}`.
4. El servidor valida la entrada, el permiso y la pertenencia al negocio de la categoría y la marca cuando se informan, guarda el producto y responde.
5. El listado muestra «—» en la columna Categoría para los productos sin categoría.

### Flujos alternativos y errores
- Categoría de otro negocio, inexistente o inactiva (que el producto no tenía): `422` en `categoria`.
- Falta de permiso: `403`. Negocio en solo lectura: `403` al modificar.

### Reglas de negocio
- La categoría es opcional; la marca sigue siendo opcional.
- Se puede quitar la categoría de un producto existente enviando `null` o texto vacío.
- Una categoría desactivada puede conservarse en un producto existente mientras no se cambie.
- El filtro de categoría del listado solo devuelve productos con esa categoría.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/productos` | Crear producto con o sin categoría. |
| PUT | `/api/v1/productos/{id}` | Editar producto, incluida la categoría. |
| GET | `/api/v1/productos` | Listar productos; `categoria` es `null` si no tienen. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "id": "01J...",
    "nombre": "Agua mineral",
    "precio": 1200,
    "activo": true,
    "categoria": null,
    "marca": null
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "categoria": ["La categoría seleccionada no está disponible."]
  }
}
```

### Tablas y columnas
- `productos`: `categoria_id` pasa a ser nullable y mantiene su referencia a `categorias`.

### Consideraciones de seguridad
- La categoría informada se valida en el servidor dentro del negocio actual; el cliente no fija `negocio_id`.
- La validación y el bloqueo transaccional existentes se mantienen para evitar asignaciones inválidas o simultáneas.
