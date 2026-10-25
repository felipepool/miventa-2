# 07-CU-validacion-catalogo

## Caso de uso: Validar los datos de los mantenedores

### Objetivo
Evitar que un precio omitido se guarde como cero y que entradas de tipo incorrecto se conviertan en texto válido.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Negocio operativo con sucursal seleccionada; para productos, una categoría activa del negocio.

### Flujo principal
1. El usuario completa un formulario de Producto, Categoría o Marca.
2. El cliente conserva la diferencia entre un precio vacío y un cero escrito expresamente.
3. El servidor recorta espacios de los campos de texto y valida sus tipos antes de guardar.
4. El servidor devuelve el registro creado o actualizado.

### Reglas de negocio
- El precio del producto es obligatorio, entero y puede valer cero cuando se informa expresamente.
- Un arreglo u otro valor no textual en un campo de texto no se convierte a cadena.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST, PUT | `/api/v1/productos`, `/api/v1/productos/{id}` | Crear o editar producto con precio obligatorio. |
| POST, PUT | `/api/v1/categorias`, `/api/v1/categorias/{id}` | Crear o editar categoría con nombre textual. |
| POST, PUT | `/api/v1/marcas`, `/api/v1/marcas/{id}` | Crear o editar marca con nombre textual. |

### Respuesta de éxito (201)
```json
{ "data": { "id": "01J...", "nombre": "Agua", "precio": 0 } }
```

### Respuesta de error (422)
```json
{ "message": "Los datos proporcionados no son válidos.", "errors": { "precio": ["El campo precio es obligatorio."] } }
```

### Tablas y columnas
- `productos`: `nombre`, `precio` y demás campos validados del producto.
- `categorias`, `marcas`: `nombre`.

### Consideraciones de seguridad
- La validación y autorización permanecen en el servidor y se aplica el aislamiento por negocio existente.
