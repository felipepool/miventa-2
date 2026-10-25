# 07-CU-opciones-primer-producto

## Caso de uso: Seleccionar categoría y marca al crear un producto

### Objetivo
Mantener disponibles las opciones del formulario aunque se recargue el listado y guiar la creación del primer producto.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Negocio operativo con sucursal seleccionada.

### Flujo principal
1. La pantalla consulta las opciones de categorías y marcas del negocio de forma independiente al listado de productos.
2. El usuario busca, filtra o pagina productos sin perder las opciones del formulario.
3. Si existe una categoría activa, el usuario puede seleccionarla y guardar el producto.
4. Si no existe, la pantalla ofrece crear una categoría antes del producto.

### Flujos alternativos y errores
- Si falla la carga de opciones, la pantalla y el formulario muestran un error y permiten reintentar sin presentar selectores vacíos como si fueran datos válidos.

### Reglas de negocio
- La categoría es obligatoria y debe estar activa para asignarla a un producto nuevo; la marca es opcional.
- La edición conserva la categoría o marca inactiva ya asignada según el caso de uso original del catálogo.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/productos` | Listado paginado sin opciones de categorías y marcas. |
| GET | `/api/v1/productos/opciones` | Categorías y marcas del negocio para filtros y formulario. |

### Respuesta de éxito (200)
```json
{ "categorias": [{ "id": "01J...", "nombre": "Bebidas", "activa": true }], "marcas": [] }
```

### Respuesta de error (403)
```json
{ "message": "Esta acción no está autorizada." }
```

### Tablas y columnas
- `categorias`, `marcas`: `public_id`, `negocio_id`, `nombre`, `activa`.

### Consideraciones de seguridad
- El endpoint exige autenticación, permiso `gestionar_catalogo` y sucursal seleccionada; los modelos se limitan al negocio actual.
