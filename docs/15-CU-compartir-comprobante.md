# 15-compartir-comprobante

## Caso de uso: Imprimir y compartir un comprobante interno

### Objetivo
Permitir imprimir un ticket de 80 o 58 mm y compartir un enlace temporal para consultarlo sin iniciar sesión.

### Actores
- Usuario autorizado para consultar la venta.
- Persona que recibe el enlace firmado.

### Precondiciones
- Venta registrada y accesible para quien solicita el enlace.

### Flujo principal
1. El usuario abre el comprobante desde la venta registrada o su detalle.
2. El servidor entrega el contenido del ticket y un enlace firmado válido por 30 días.
3. El usuario selecciona papel de 80 o 58 mm e imprime desde el navegador, o comparte el enlace por el menú del celular, WhatsApp o copia.
4. La persona que recibe el enlace consulta el mismo ticket sin iniciar sesión mientras la firma siga vigente.

### Reglas de negocio
- El ticket muestra negocio, sucursal, folio, fecha, cajero, líneas y modificadores, descuentos, redondeo, total, pagos, vuelto, anulación y devoluciones cuando corresponda.
- El ticket incluye la leyenda «Comprobante interno, no válido como boleta».
- El enlace vence a los 30 días y la ruta pública limita las solicitudes.

### Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/ventas/{venta}/enlace-comprobante` | Obtener ticket y enlace firmado. |
| GET | `/api/v1/comprobantes/{venta}` | Consultar ticket con firma válida. |

### Respuesta de éxito (200, extracto)
```json
{"data":{"folio":5,"total":5900,"items":[{"nombre":"Producto"}]}}
```

### Respuesta de error (403, extracto)
```json
{"message":"Invalid signature."}
```

### Tablas y columnas
- `ventas`: `public_id`, `numero_sucursal`, `pagada_at`, `estado`, `total`, `redondeo`, `anulada_at`.
- `venta_items`: `nombre`, `cantidad`, `precio_unitario`, `subtotal`, `descuento`, `descuento_venta`.
- `venta_item_modificadores`, `pagos`, `devoluciones`, `devolucion_items`: contenido visible del ticket.
- `negocios`, `sucursales`, `users`: identificación mostrada en el ticket.

### Consideraciones de seguridad
- Solo quien puede consultar la venta obtiene el enlace.
- La ruta pública valida firma y vigencia, aplica límite de solicitudes y omite datos internos como costos, identificadores y nombre completo del cajero.
