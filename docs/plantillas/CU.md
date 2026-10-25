# NN-nombre-del-caso

## Caso de uso: Nombre del caso de uso

### Objetivo
Qué permite lograr este caso de uso y a quién.

### Actores
- Actor que participa (rol o permiso requerido).

### Precondiciones
- Estado o datos que deben existir antes de ejecutarlo.

### Flujo principal
1. El cliente envía `MÉTODO /api/ruta` con el cuerpo `{ campo }`.
2. El servidor valida la entrada y la autorización (pertenencia al negocio y a la sucursal).
3. El servidor ejecuta la operación y responde.

### Flujos alternativos y errores
- Condición que falla: respuesta y mensaje (sin revelar datos sensibles).

### Reglas de negocio
- Regla acordada en el plan aprobado.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/ruta` | Descripción. |

### Respuesta de éxito (200)
```json
{
  "data": {}
}
```

### Respuesta de error (422)
```json
{
  "message": "Mensaje de error"
}
```

### Tablas y columnas
- `tabla`: `id`, `public_id`, `negocio_id`, `created_at`, `updated_at`

### Consideraciones de seguridad
- Validación y autorización en el servidor; el contexto de negocio sale de la sesión autenticada, nunca del cliente.
