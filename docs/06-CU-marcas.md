# 06-CU-marcas

## Caso de uso: Administrar marcas

### Objetivo
Permitir que el negocio organice productos por marca y conserve el historial al desactivarlas.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Hay una sucursal activa seleccionada y el negocio permite escritura.

### Flujo principal
1. El usuario lista marcas con búsqueda opcional `buscar`, filtro `estado` (`activa` o `inactiva`) y paginación `page`.
2. El usuario crea o edita una marca enviando `{ "nombre": "Acme" }`.
3. El usuario cambia el estado con `PATCH /api/v1/marcas/{id}/estado` y `{ "activa": false }`.
4. El servidor valida el permiso, el negocio y el nombre único; responde con la marca actualizada y el número de productos asociados.

### Flujos alternativos y errores
- Nombre vacío, demasiado largo o repetido en el negocio: `422` con error de validación.
- Marca de otro negocio o inexistente: `404`.
- Falta de permiso: `403`.
- Negocio en solo lectura: `403` al modificar.

### Reglas de negocio
- No se eliminan marcas; se activan o desactivan.
- Desactivar una marca no altera productos existentes; no se puede asignar a productos nuevos ni reemplazar otra marca por ella.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/marcas?buscar=&estado=&page=` | Listar y filtrar marcas. |
| POST | `/api/v1/marcas` | Crear marca. |
| GET | `/api/v1/marcas/{id}` | Consultar marca. |
| PUT | `/api/v1/marcas/{id}` | Cambiar nombre. |
| PATCH | `/api/v1/marcas/{id}/estado` | Activar o desactivar. |

### Respuesta de éxito (200)
```json
{
  "data": {
    "id": "01J...",
    "nombre": "Acme",
    "activa": true,
    "productos_count": 3
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "nombre": ["Ya existe una marca con este nombre en tu negocio."]
  }
}
```

### Tablas y columnas
- `marcas`: `id`, `public_id`, `negocio_id`, `nombre`, `activa`, `created_at`, `updated_at`.
- `productos`: `marca_id` referencia marcas sin eliminar su historial.

### Consideraciones de seguridad
- Sanctum autentica las solicitudes; la ruta exige `gestionar_catalogo` y sucursal seleccionada.
- El negocio se obtiene del contexto autorizado, no de datos enviados por el cliente. El modelo limita el acceso por negocio.
- `negocio_id`, `activa` y el identificador interno no se aceptan al crear o editar.
