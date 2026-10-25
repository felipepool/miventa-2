# 06-CU-categorias

## Caso de uso: Administrar categorías

### Objetivo
Permitir al negocio clasificar sus productos y conservar esa clasificación en el historial.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Hay una sucursal activa seleccionada y el negocio permite escritura.

### Flujo principal
1. El usuario lista categorías con búsqueda opcional `buscar`, filtro `estado` (`activa` o `inactiva`) y paginación `page`.
2. El usuario crea o edita una categoría enviando `{ "nombre": "Bebidas" }`.
3. El usuario cambia el estado con `PATCH /api/v1/categorias/{id}/estado` y `{ "activa": false }`.
4. El servidor valida el permiso, el negocio y el nombre único; responde con la categoría y el número de productos asociados.

### Flujos alternativos y errores
- Nombre vacío, demasiado largo o repetido en el negocio: `422` con error de validación.
- Categoría de otro negocio o inexistente: `404`.
- Falta de permiso: `403`.
- Negocio en solo lectura: `403` al modificar.

### Reglas de negocio
- No se eliminan categorías; se activan o desactivan.
- Desactivar una categoría no altera productos existentes; no se puede asignar a productos nuevos ni reemplazar otra categoría por ella.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/categorias?buscar=&estado=&page=` | Listar y filtrar categorías. |
| POST | `/api/v1/categorias` | Crear categoría. |
| GET | `/api/v1/categorias/{id}` | Consultar categoría. |
| PUT | `/api/v1/categorias/{id}` | Cambiar nombre. |
| PATCH | `/api/v1/categorias/{id}/estado` | Activar o desactivar. |

### Respuesta de éxito (200)
```json
{
  "data": {
    "id": "01J...",
    "nombre": "Bebidas",
    "activa": true,
    "productos_count": 12
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "nombre": ["Ya existe una categoría con este nombre en tu negocio."]
  }
}
```

### Tablas y columnas
- `categorias`: `id`, `public_id`, `negocio_id`, `nombre`, `activa`, `created_at`, `updated_at`.
- `productos`: `categoria_id` referencia categorías sin eliminar su historial.

### Consideraciones de seguridad
- Sanctum autentica las solicitudes; la ruta exige `gestionar_catalogo` y sucursal seleccionada.
- El negocio se obtiene del contexto autorizado, no de datos enviados por el cliente. El modelo limita el acceso por negocio.
- `negocio_id`, `activa` y el identificador interno no se aceptan al crear o editar.
