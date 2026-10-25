# 06-CU-productos

## Caso de uso: Administrar productos

### Objetivo
Permitir al negocio crear y mantener su catálogo de productos para usarlo en operaciones futuras.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Hay una sucursal activa seleccionada, el negocio permite escritura y existe una categoría activa del negocio.

### Flujo principal
1. El usuario lista productos con búsqueda por nombre, SKU o código de barras, filtros opcionales de categoría, marca y estado, y paginación.
2. El usuario crea o edita un producto con categoría, nombre, unidad de venta, precio entero en pesos con IVA incluido y los demás campos permitidos.
3. El usuario cambia el estado con `PATCH /api/v1/productos/{id}/estado` y `{ "activo": false }`.
4. El servidor verifica permiso, unicidad y pertenencia al negocio de las relaciones, y responde con el producto y sus nombres de categoría y marca.

### Flujos alternativos y errores
- Campos inválidos, categoría o marca inactiva no asignable, SKU o código de barras duplicado: `422`.
- Producto de otro negocio o inexistente: `404`.
- Falta de permiso: `403`.
- Negocio en solo lectura: `403` al modificar.

### Reglas de negocio
- La categoría es obligatoria y la marca opcional; ambas deben pertenecer al mismo negocio.
- Categoría o marca desactivada puede conservarse en un producto existente mientras no se cambie.
- SKU y código de barras, cuando se informan, son únicos dentro del negocio.
- Precio y costo son montos enteros en pesos; el precio incluye IVA.
- Los productos no se eliminan; se activan o desactivan.
- Tipo venta/insumo, recetas, imágenes, importación/exportación, modificadores e inventario quedan fuera de este alcance.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/productos?buscar=&categoria=&marca=&estado=&page=` | Listar, buscar y filtrar productos. |
| GET | `/api/v1/productos/opciones` | Obtener opciones de categoría y marca para filtros y formulario. |
| POST | `/api/v1/productos` | Crear producto. |
| GET | `/api/v1/productos/{id}` | Consultar producto. |
| PUT | `/api/v1/productos/{id}` | Editar producto. |
| PATCH | `/api/v1/productos/{id}/estado` | Activar o desactivar. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "id": "01J...",
    "nombre": "Agua mineral",
    "sku": "AGUA-01",
    "codigo_barras": null,
    "descripcion": null,
    "unidad_venta": "unidad",
    "precio": 1200,
    "costo": null,
    "controla_stock": true,
    "activo": true,
    "categoria": { "id": "01J...", "nombre": "Bebidas", "activa": true },
    "marca": null
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "categoria": ["La categoría seleccionada no está disponible."],
    "sku": ["Ya existe un producto con este SKU en tu negocio."]
  }
}
```

### Tablas y columnas
- `productos`: `id`, `public_id`, `negocio_id`, `categoria_id`, `marca_id`, `sku`, `codigo_barras`, `nombre`, `descripcion`, `unidad_venta`, `precio`, `costo`, `controla_stock`, `activo`, `created_at`, `updated_at`.
- `categorias`: `id`, `public_id`, `negocio_id`, `nombre`, `activa`.
- `marcas`: `id`, `public_id`, `negocio_id`, `nombre`, `activa`.

### Consideraciones de seguridad
- Sanctum autentica las solicitudes; la ruta exige `gestionar_catalogo` y sucursal seleccionada.
- El contexto autorizado establece el negocio; las relaciones se resuelven dentro de él y los identificadores internos no se exponen.
- Se validan todos los campos en el servidor; el cliente no fija `negocio_id` ni `activo`.
- Restricciones únicas de base de datos y bloqueo transaccional protegen frente a duplicados simultáneos.
