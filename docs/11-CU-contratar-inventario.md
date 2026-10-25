# 11-contratar-inventario

## Caso de uso: Contratar el adicional Inventario

### Objetivo
Permitir al administrador del negocio contratar Inventario como adicional mensual desde Mi plan, con el cobro explícito y el precio aceptado conservado.

### Actores
- Usuario autenticado con permiso `gestionar_negocio`.

### Precondiciones
- Hay una sucursal activa seleccionada y el plan tiene la tarifa de Inventario configurada.

### Flujo principal
1. El administrador abre Mi plan y marca «Inventario» entre los adicionales.
2. El cliente consulta `GET /api/v1/plan/cotizacion?plan=comercio&adicionales[]=inventario` y recibe el desglose mensual y un código de cotización.
3. El administrador acepta el cobro y el cliente envía `PUT /api/v1/plan` con `{ "plan": "retail", "adicionales": ["inventario"], "acepto_cobro": true, "cotizacion": "..." }`.
4. El servidor valida el permiso, que la cotización siga vigente y la aceptación, y registra el contrato con la foto del precio, quién aceptó y cuándo.
5. Si el negocio está en prueba, el cobro comienza al terminar los 14 días; si no, rige desde ahora.

### Flujos alternativos y errores
- Cotización desactualizada porque cambió una tarifa o los recursos: `422` en `cotizacion`.
- Cobro no aceptado: `422` en `acepto_cobro`. Tarifa sin precio configurado: `422` en `tarifa`.
- Falta de permiso: `403`. Negocio sin sucursal seleccionada: `409`.
- Si el administrador desmarca el adicional al confirmar, el contrato vigente se cierra y los datos se conservan.

### Reglas de negocio
- Inventario cuesta 0,5 UF mensuales + IVA en los planes Comercio, Gastronomía y Mixto.
- Durante la prueba de 14 días está habilitado sin contratarlo; después solo si está contratado.
- El precio contratado no cambia aunque se modifique la tarifa después.
- Dejar de contratarlo no borra el stock ni el historial; se recuperan al contratarlo otra vez.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/plan/cotizacion?plan=&adicionales[]=` | Cotizar el plan con los adicionales elegidos. |
| PUT | `/api/v1/plan` | Contratar el plan y sus adicionales con la cotización aceptada. |

### Respuesta de éxito (200)
```json
{
  "data": {
    "adicionales_contratados": ["inventario"],
    "estado": { "adicionales_activos": ["inventario"] }
  }
}
```

### Respuesta de error (422)
```json
{
  "message": "Las condiciones han cambiado. Vuelve a revisar el plan antes de aceptar.",
  "errors": { "cotizacion": ["Las condiciones han cambiado. Vuelve a revisar el plan antes de aceptar."] }
}
```

### Tablas y columnas
- `tarifas`: `concepto` (`inventario`), `plan`, `precio_uf`.
- `negocio_adicionales`: `negocio_id`, `tarifa_id`, `concepto`, `plan`, `precio_uf`, `desde`, `hasta`, `aceptado_por_id`, `aceptado_at`; las condiciones contratadas no se pueden modificar.

### Consideraciones de seguridad
- Permiso `gestionar_negocio` y aislamiento por negocio validados en el servidor.
- El negocio se bloquea durante la contratación para evitar contratos duplicados por envíos repetidos o simultáneos.
- La cotización firmada impide aceptar un precio distinto al vigente.
