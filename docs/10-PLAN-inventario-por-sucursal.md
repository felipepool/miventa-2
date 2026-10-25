# 10-inventario-por-sucursal

1. **Base de datos y reglas.** Tabla `stocks` (producto, sucursal, `cantidad` y `stock_minimo`), única por producto y sucursal y aislada por negocio y por sucursal. La cantidad es un decimal de 3 posiciones en la unidad del producto y la API la devuelve como texto, sin `float`. Tabla `movimientos_stock`: historial definitivo (no se edita ni se elimina) con producto, sucursal, tipo (`entrada`, `salida` o `ajuste`), variación con signo, stock resultante, motivo, usuario y fecha. Solo los productos con `controla_stock` activo tienen inventario; el stock inicial se carga con un ajuste. Cada movimiento bloquea la fila del stock dentro de una transacción, y no se permite stock negativo.

2. **API.** Bajo el permiso `gestionar_inventario` y con sucursal seleccionada: `GET /api/v1/inventario` (stock de la sucursal actual, con búsqueda, filtros por categoría y stock bajo, y paginación), `POST /api/v1/inventario/{producto}/movimientos`, `GET /api/v1/inventario/{producto}/movimientos` y `PUT /api/v1/inventario/{producto}/minimo`. Los productos de otro negocio responden `404`; el cliente nunca fija negocio, sucursal, usuario ni el stock resultante.

3. **Pantalla Inventario.** Nueva entrada en el menú, visible con `gestionar_inventario`, con el flujo de Usuarios: listado con búsqueda y filtros en la URL, columnas de producto, stock, mínimo y la etiqueta «Stock bajo», formulario para registrar movimientos con motivo, historial por producto y definición del stock mínimo, con carga, error y reintento. Los envíos no se duplican y la pantalla funciona en escritorio y móviles.

4. **Verificar y entregar.** Pruebas en MySQL (`miventa_test`): movimientos válidos, rechazo de stock negativo, cantidades decimales, aislamiento entre sucursales y negocios, permisos y concurrencia. Guardar este plan y sus casos de uso en `docs/` y hacer el commit. Quedan fuera: el descuento de stock por ventas y recetas, compras y proveedores, transferencias entre sucursales, lotes y vencimientos, y las notificaciones de stock bajo (solo hay una etiqueta en el listado).

## Verificación

- Entrada, salida y ajuste actualizan el stock y quedan en el historial con su variación y el stock resultante.
- Una salida mayor al stock disponible se rechaza con `422` y no modifica nada; dos salidas simultáneas nunca dejan el stock en negativo.
- Los productos por unidad solo admiten cantidades enteras; el resto, hasta 3 decimales.
- El stock y el historial de una sucursal no se mezclan con los de otra ni con los de otro negocio.
- Sin el permiso `gestionar_inventario` todas las rutas responden `403`.
