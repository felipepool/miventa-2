# 15-anular-ventas-devoluciones-y-comprobantes

1. **Base de datos y reglas.**
   - **Anulación.** `ventas` suma `anulada_at`, `anulada_por` (usuario) y `motivo_anulacion`.
     - Se anula una venta `pagada` de la sucursal actual, con motivo y el permiso `anular_ventas`, solo mientras su turno de caja sigue abierto y si no tiene devoluciones.
     - Pasa a `anulada`. Su efectivo sale solo del esperado de ese turno.
     - Con el adicional Inventario activo, el stock descontado se reintegra con movimientos de tipo `anulacion`.
     - No se deshace ni se anula dos veces: dos anulaciones simultáneas dejan una sola.
   - **Devolución.** Tablas `devoluciones` y `devolucion_items`.
     - `devoluciones` guarda la venta, el turno donde se devuelve, el usuario, el medio, el monto, el motivo, si reintegra stock y una clave de idempotencia.
     - `devolucion_items` guarda la línea de la venta, la cantidad y el monto.
     - Una devolución es total o por líneas y cantidades, con hasta 3 decimales en productos por medida.
     - Nunca supera lo vendido menos lo ya devuelto en cada línea.
     - Exige el permiso `anular_ventas` y un turno abierto de quien devuelve. Puede ser de una venta de un turno ya cerrado, incluso de otra persona, siempre de la misma sucursal.
   - **Monto devuelto.** Lo calcula el servidor: la parte proporcional del total cobrado de cada línea, ya con sus descuentos y el descuento a la venta, en pesos enteros.
     - El último reembolso de una línea toma el resto, para que la suma no supere lo cobrado.
     - Medios: efectivo, débito, crédito o transferencia, y la pantalla propone el de la venta.
     - En efectivo, el monto se redondea al múltiplo de $10 más cercano, como en el cobro, y baja el esperado del turno de quien devuelve.
     - Reintegrar el stock es opcional, por ejemplo si el producto vuelve dañado. Si se reintegra, se registran movimientos de tipo `devolucion`.
   - **Estado de la venta.** Una venta con devoluciones sigue `pagada` y muestra «con devolución» o «devuelta», según lo devuelto.

2. **API.** Todo requiere la sucursal seleccionada, y el negocio en solo lectura bloquea anular y devolver.
   - `POST /api/v1/ventas/{venta}/anular` con el motivo.
   - `POST /api/v1/ventas/{venta}/devoluciones` con las líneas y cantidades, el medio, el motivo, si reintegra stock y la clave de idempotencia.
   - `GET /api/v1/ventas/{venta}` suma la anulación y las devoluciones con sus líneas.
   - `GET /api/v1/ventas` filtra por estado: pagada, anulada o con devolución.
   - `GET /api/v1/turnos/{turno}` y Mi caja muestran las devoluciones en efectivo en el resumen y el esperado.
   - **Enlace público.** `GET /api/v1/ventas/{venta}/enlace-comprobante` entrega un enlace firmado que expira a los 30 días.
     - `GET /api/v1/comprobantes/{venta}` responde sin sesión, solo con la firma válida y con límite de solicitudes.
     - Muestra solo lo del ticket, sin datos internos como costos, el usuario completo o los ids.

3. **Pantallas.**
   - **Detalle de venta.** Con `anular_ventas` aparecen los botones **Anular** y **Devolver**. Siguen el flujo de diálogos de Usuarios, con carga, error y reintento, y no duplican envíos.
     - **Anular:** diálogo con el motivo. Solo aparece si el turno sigue abierto y la venta no tiene devoluciones. Si no aplica, explica por qué y sugiere devolver.
     - **Devolver:** diálogo con las líneas y la cantidad a devolver, el medio, el motivo, si reintegra stock y el monto calculado.
     - La venta muestra el sello «Anulada» o la lista de devoluciones.
   - **Historial.** Etiqueta de estado y filtro por estado.
   - **Comprobante.**
     - Botón **Imprimir ticket**: abre la vista del ticket de 80 o 58 mm y la imprime desde el navegador.
     - Lleva el nombre del negocio y la sucursal, el folio, la fecha, el cajero, las líneas con sus modificadores y descuentos, el redondeo, el total, los pagos y el vuelto.
     - Muestra «Anulada» o las devoluciones, y la leyenda «Comprobante interno, no válido como boleta».
     - Botón **Compartir**: usa el menú de compartir del celular con un resumen y el enlace público. Si no está disponible, abre WhatsApp con el texto o copia el enlace.
     - La página pública del enlace (`/comprobante/...`) muestra el mismo ticket sin iniciar sesión.
     - El diálogo «Venta registrada» suma **Imprimir ticket** y **Compartir**.

4. **Verificar y entregar.**
   - Pruebas en MySQL (`miventa_test`):
     - Anular con el turno abierto y rechazarlo con el turno cerrado o si ya tiene devoluciones.
     - Efecto en el esperado y en el stock, con y sin el adicional Inventario.
     - Devolución parcial y total, el tope de lo ya devuelto, el reparto proporcional y el redondeo en efectivo.
     - Medios de reembolso, idempotencia y concurrencia (dos devoluciones o anulaciones simultáneas).
     - Permisos, solo lectura, aislamiento entre sucursales y negocios.
     - Enlace público válido, vencido y alterado.
   - Revisión de las pantallas en escritorio, tablet y celular.
   - Guardar el plan y sus casos de uso en `docs/` y hacer el commit.
   - Quedan fuera: boleta y factura electrónica (SII), cambio de producto en una devolución (se hace como devolución más venta nueva), notas de crédito, envío del comprobante por correo e impresión directa sin el diálogo del navegador.

## Correcciones aprobadas

1. Aplicar a todos los medios de devolución el saldo realmente cobrado menos lo ya devuelto, incluidos los redondeos. Cubrir devoluciones sucesivas y combinadas entre efectivo, tarjeta y transferencia.
2. Validar la idempotencia: repetir la misma solicitud devuelve el resultado existente; reutilizar la clave para otra venta o con datos diferentes responde 409 sin modificar dinero ni stock.
3. Cargar nuevamente la venta con `EditDialog` antes de montar `FormDialog` para anular o devolver. Compartir carga, error, reintento y registro no disponible; conservar la protección contra envíos duplicados.
4. Completar los casos de uso, ejecutar pruebas enfocadas y concurrencia en MySQL, TypeScript y Pint; revisar los diálogos en tamaños de escritorio, Android, iPhone, iPad y otras tablets, indicando los límites de la emulación.

## Verificación

- Una venta se anula solo con su turno abierto, sin devoluciones y con `anular_ventas`. Anulada, sale del esperado y, con Inventario, reintegra el stock.
- Una devolución nunca supera lo vendido. El monto lo calcula el servidor, y en efectivo se redondea a $10 y baja el esperado del turno de quien devuelve.
- Repetir el envío con la misma clave no duplica la devolución, y dos anulaciones simultáneas dejan una sola.
- El enlace público funciona sin sesión solo con una firma válida y vigente, y no expone datos internos.
- El cajero sin `anular_ventas` no ve los botones y la API lo rechaza. Otra sucursal u otro negocio no ve la venta.
- Pantallas revisadas en escritorio, tablet y celular, con los diálogos abiertos y el ticket impreso en vista previa.
