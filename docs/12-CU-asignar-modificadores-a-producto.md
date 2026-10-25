# 12-asignar-modificadores-a-producto

## Caso de uso: Asignar grupos de modificadores a un producto

### Objetivo
Permitir que cada producto ofrezca, al venderse, los grupos de modificadores que correspondan, en el orden que el negocio defina.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Hay una sucursal activa seleccionada, el negocio permite escritura y el módulo Modificadores está activo.
- Existe al menos un grupo de modificadores activo.

### Flujo principal
1. El usuario abre el formulario de un producto nuevo o existente y, en la sección «Grupos de modificadores», elige los grupos y ordena su aparición.
2. El cliente envía `POST /api/v1/productos` o `PUT /api/v1/productos/{id}` con `grupos_modificadores: ["01J...", "01J..."]`, en el orden elegido.
3. El servidor valida que cada grupo sea del negocio y esté activo (o ya estuviera asignado al producto) y reemplaza las asignaciones del producto por la lista recibida.
4. La respuesta incluye los grupos asignados, con sus modificadores, en orden.

### Flujos alternativos y errores
- Grupo de otro negocio, inexistente o inactivo que el producto no tenía: `422` en `grupos_modificadores`.
- Grupo repetido en la lista o más de 20 grupos: `422`.
- Con el módulo apagado el formulario no muestra la sección y la API ignora `grupos_modificadores` sin quitar las asignaciones existentes.
- Falta de permiso: `403`. Negocio en solo lectura: `403`.

### Reglas de negocio
- La asignación se hace solo desde el formulario del producto.
- Un grupo puede asignarse a varios productos y un producto puede tener hasta 20 grupos.
- Un grupo o modificador desactivado puede conservarse en un producto existente mientras no se quite; no puede asignarse a otros.
- Enviar una lista vacía quita todos los grupos del producto.
- Desactivar el módulo oculta la función y conserva las asignaciones.
- La selección y el cobro de modificadores en la venta quedan fuera de este caso de uso.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/productos` | Crear un producto con sus grupos de modificadores. |
| PUT | `/api/v1/productos/{id}` | Editar los grupos de modificadores de un producto. |
| GET | `/api/v1/productos/opciones` | Incluye los grupos de modificadores disponibles para elegir. |

### Respuesta de éxito (200)
```json
{
  "data": {
    "id": "01J...",
    "nombre": "Completo italiano",
    "grupos_modificadores": [
      { "id": "01J...", "nombre": "Tipo de pan", "tipo_seleccion": "unica", "activo": true },
      { "id": "01J...", "nombre": "Salsas", "tipo_seleccion": "cantidad", "activo": true }
    ]
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "grupos_modificadores": ["Uno de los grupos de modificadores no está disponible."]
  }
}
```

### Tablas y columnas
- `producto_grupo_modificador`: `producto_id`, `grupo_modificador_id`, `orden`; clave primaria compuesta por producto y grupo.
- `grupos_modificadores`: `public_id`, `negocio_id`, `activo`.

### Consideraciones de seguridad
- Cada grupo se resuelve dentro del negocio actual; un grupo de otro negocio se trata como inexistente.
- El cliente no fija el `negocio_id` ni el orden interno: el servidor deriva el orden de la posición en la lista.
- La actualización se hace en transacción con el bloqueo del negocio, igual que el resto del producto.
