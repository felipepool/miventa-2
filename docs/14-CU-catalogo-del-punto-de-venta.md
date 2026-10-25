# 14-catalogo-del-punto-de-venta

## Caso de uso: Consultar los productos a la venta en el punto de venta

### Objetivo
Entregar a la pantalla de venta los productos que se pueden vender, con lo necesario para armar la venta sin más consultas.

### Actores
- Usuario autenticado con permiso `vender`.

### Precondiciones
- Hay una sucursal activa seleccionada.

### Flujo principal
1. La pantalla Vender envía `GET /api/v1/pos`.
2. El servidor devuelve los productos activos, a la venta y con precio del negocio, con su categoría y sus grupos de modificadores activos (con sus opciones activas) cuando el módulo Modificadores está activo.
3. Devuelve también las categorías activas, los medios de pago, el tope de descuento del cajero, si el usuario puede descontar sin límite y el turno abierto del usuario.
4. La pantalla filtra y busca en el navegador por nombre, SKU y código de barras.

### Flujos alternativos y errores
- Sin sucursal seleccionada: `409`. Falta de permiso: `403`.
- Sin turno abierto: la respuesta trae `turno: null` y la pantalla invita a abrir la caja.

### Reglas de negocio
- No se listan productos inactivos, que solo son insumos, sin precio o de otro negocio.
- Los modificadores aparecen solo si el módulo Modificadores está activo; un grupo o una opción desactivados no se ofrecen.
- El precio es el vigente con IVA incluido; la pantalla nunca lo envía de vuelta: el servidor lo vuelve a leer al vender.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/pos` | Catálogo y parámetros de la pantalla de venta. |

### Respuesta de éxito (200)
```json
{
  "data": {
    "productos": [{
      "id": "01J...", "nombre": "Completo", "sku": null, "codigo_barras": null, "precio": 3000, "unidad_venta": "unidad",
      "categoria": { "id": "01J...", "nombre": "Sándwiches" },
      "grupos_modificadores": [{ "id": "01J...", "nombre": "Pan", "tipo_seleccion": "unica", "minimo": 1, "maximo": 1, "cantidad_gratis": null, "precio_extra": 0,
        "modificadores": [{ "id": "01J...", "nombre": "Amasado", "precio_adicional": 0, "cantidad_maxima": null }] }]
    }],
    "categorias": [{ "id": "01J...", "nombre": "Sándwiches" }],
    "medios_pago": [{ "value": "efectivo", "label": "Efectivo" }],
    "descuento_maximo": 10,
    "descuento_sin_limite": false,
    "turno": { "id": "01J...", "numero": 12, "caja": "Caja 1" }
  }
}
```

### Respuesta de error (409)
```json
{
  "message": "Selecciona una sucursal disponible para continuar.",
  "code": "sucursal_requerida"
}
```

### Tablas y columnas
- `productos`, `categorias`, `grupos_modificadores`, `modificadores`, `producto_grupo_modificador` y `turnos_caja`: solo lectura.

### Consideraciones de seguridad
- Solo se entregan datos del negocio autenticado y no se exponen identificadores internos ni el costo de los productos.
- El permiso `vender` y la sucursal seleccionada se exigen en la ruta.
