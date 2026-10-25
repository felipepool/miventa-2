# 11-acceso-inventario-contratado

## Caso de uso: Usar Inventario solo si el adicional está activo

### Objetivo
Garantizar que Inventario solo se use cuando el negocio lo tiene en prueba o contratado, y explicar con claridad cómo activarlo cuando no es así.

### Actores
- Usuario autenticado con permiso `gestionar_inventario`.
- Administrador con permiso `gestionar_negocio`, que puede contratarlo.

### Precondiciones
- Hay una sucursal activa seleccionada.

### Flujo principal
1. El usuario inicia sesión; la sesión informa los adicionales activos del negocio (`adicionales_activos`).
2. Si «inventario» está activo y el usuario tiene el permiso, el menú muestra «Inventario» y las rutas `/api/v1/inventario` funcionan.
3. Si el adicional no está activo, el menú no muestra el ítem.
4. Si el usuario entra igualmente a `/inventario`, ve la pantalla «Inventario no está contratado»: el administrador con `gestionar_negocio` encuentra el enlace «Ir a Mi plan» y los demás usuarios, un aviso para consultar con el administrador.

### Flujos alternativos y errores
- Cualquier ruta de `/api/v1/inventario` sin el adicional activo: `403` con `code: adicional_no_contratado`.
- Sin el permiso `gestionar_inventario`: `403` sin ese código, aunque el adicional esté activo.
- Contrato del adicional cerrado: se comporta como no contratado desde ese momento.

### Reglas de negocio
- Adicional activo = prueba vigente o contrato abierto cuya fecha de inicio ya llegó.
- El permiso y el adicional son requisitos independientes: hacen falta ambos.
- El stock y el historial se conservan aunque el adicional no esté activo.
- La condición es reutilizable para otros adicionales (mesas, KDS, facturación) con `adicional:<concepto>`.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/inventario` | Listar el inventario; exige adicional activo. |
| GET, POST | `/api/v1/inventario/{producto}/movimientos` | Historial y registro de movimientos; exige adicional activo. |
| PUT | `/api/v1/inventario/{producto}/minimo` | Definir el stock mínimo; exige adicional activo. |

### Respuesta de éxito (200)
```json
{
  "data": [],
  "categorias": []
}
```

### Respuesta de error (403)
```json
{
  "message": "Inventario no está contratado en tu plan. Contrátalo desde Mi plan o consulta con el administrador del negocio.",
  "code": "adicional_no_contratado",
  "adicional": "inventario"
}
```

### Tablas y columnas
- `negocio_adicionales`: `concepto`, `desde`, `hasta`.
- `negocios`: `prueba_desde`, `prueba_hasta`.

### Consideraciones de seguridad
- La restricción se aplica en el servidor mediante el middleware `adicional:inventario`; ocultar el menú y la pantalla es solo apoyo visual.
- El adicional se evalúa para el negocio de la sesión; un negocio no habilita a otro.
- El mensaje de error no revela datos de otros negocios ni de precios.
