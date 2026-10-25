# 12-grupos-modificadores

## Caso de uso: Administrar grupos de modificadores y sus modificadores

### Objetivo
Permitir al negocio crear los grupos de opciones que se ofrecen al vender un producto (tipo de pan, agregados, salsas) con sus modificadores, sin costo o con un costo adicional definido por el usuario.

### Actores
- Usuario autenticado con permiso `gestionar_catalogo`.

### Precondiciones
- Hay una sucursal activa seleccionada, el negocio permite escritura y el módulo Modificadores está activo (incluido en los planes Gastronomía y Mixto).

### Flujo principal
1. El usuario abre Catálogo → Modificadores y lista los grupos con búsqueda `buscar`, filtro `estado` (`activo` o `inactivo`) y paginación.
2. El usuario crea o edita un grupo enviando `POST /api/v1/grupos-modificadores` o `PUT /api/v1/grupos-modificadores/{id}` con los datos del grupo y la lista completa de sus modificadores.
3. El servidor valida las reglas del grupo y de cada modificador, y confirma la pertenencia al negocio.
4. En la edición, los modificadores existentes se identifican por su `id`; los que no se reciben quedan inactivos y los nuevos se crean.
5. El usuario activa o desactiva el grupo con `PATCH /api/v1/grupos-modificadores/{id}/estado` y `{ "activo": false }`.

### Flujos alternativos y errores
- Datos inválidos: `422` en el campo que corresponda (reglas incoherentes, precios fuera de rango, nombre repetido, grupo sin modificadores).
- Grupo de otro negocio o inexistente: `404`. Módulo Modificadores apagado: `404`.
- Falta de permiso: `403`. Negocio en solo lectura: `403` al modificar. Sin sucursal seleccionada: `409`.

### Reglas de negocio
- Tipos de selección: `unica` (una opción), `multiple` (varias opciones, cada una sí o no) y `cantidad` (varias unidades de cada opción).
- En un grupo de selección única el mínimo es 0 o 1 y el máximo no supera 1. En los demás, el máximo es opcional y no puede ser menor que el mínimo.
- `cantidad_gratis` y `precio_extra` (pesos enteros, IVA incluido): cada unidad que supera las gratis cobra el precio extra.
- Cada modificador tiene `precio_adicional` en pesos enteros con IVA incluido; `0` significa sin costo adicional. `cantidad_maxima` solo aplica a grupos de tipo cantidad.
- El nombre del grupo es único dentro del negocio y el del modificador, dentro del grupo (sin distinguir mayúsculas).
- Un grupo tiene entre 1 y 50 modificadores y como máximo 50 unidades configurables.
- No se eliminan grupos ni modificadores: se desactivan. Desactivar no altera los productos que ya los usan.
- Elegir y cobrar modificadores en la venta, y su efecto en insumos y recetas, quedan fuera de este caso de uso.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/grupos-modificadores?buscar=&estado=&page=` | Listar y filtrar grupos. |
| POST | `/api/v1/grupos-modificadores` | Crear un grupo con sus modificadores. |
| GET | `/api/v1/grupos-modificadores/{id}` | Consultar un grupo con sus modificadores. |
| PUT | `/api/v1/grupos-modificadores/{id}` | Editar el grupo y sus modificadores. |
| PATCH | `/api/v1/grupos-modificadores/{id}/estado` | Activar o desactivar el grupo. |

### Respuesta de éxito (201)
```json
{
  "data": {
    "id": "01J...",
    "nombre": "Salsas",
    "referencia": null,
    "tipo_seleccion": "cantidad",
    "minimo": 0,
    "maximo": 4,
    "cantidad_gratis": 2,
    "precio_extra": 300,
    "activo": true,
    "productos_count": 0,
    "modificadores": [
      { "id": "01J...", "nombre": "Mayonesa", "precio_adicional": 0, "cantidad_maxima": 2, "orden": 0, "activo": true },
      { "id": "01J...", "nombre": "Ají pebre", "precio_adicional": 200, "cantidad_maxima": null, "orden": 1, "activo": true }
    ]
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "maximo": ["El máximo no puede ser menor que el mínimo."],
    "modificadores": ["Agrega al menos un modificador."]
  }
}
```

### Tablas y columnas
- `grupos_modificadores`: `id`, `public_id`, `negocio_id`, `nombre`, `referencia`, `tipo_seleccion`, `minimo`, `maximo`, `cantidad_gratis`, `precio_extra`, `activo`, `created_at`, `updated_at`.
- `modificadores`: `id`, `public_id`, `grupo_modificador_id`, `nombre`, `precio_adicional`, `cantidad_maxima`, `orden`, `activo`, `created_at`, `updated_at`.

### Consideraciones de seguridad
- Sanctum autentica las solicitudes; la ruta exige `gestionar_catalogo`, sucursal seleccionada y el módulo Modificadores activo.
- El negocio sale del contexto autorizado; el cliente no fija `negocio_id` ni el grupo al que pertenece cada modificador, y los identificadores internos no se exponen.
- Todos los campos se validan en el servidor; el bloqueo transaccional del negocio y las restricciones únicas evitan duplicados por solicitudes simultáneas.
