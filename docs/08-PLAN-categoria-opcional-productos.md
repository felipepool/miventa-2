# 08-categoria-opcional-productos

1. **Base de datos y API.** Una migración nueva vuelve nullable `productos.categoria_id` sin alterar los datos existentes. `categoria` pasa a ser opcional en la creación y edición de productos; cuando se informa, debe ser del negocio y estar activa (o ser la que el producto ya tenía). `ProductoResource` devuelve `categoria: null` si el producto no tiene.

2. **Pantallas de Productos.** El formulario muestra «Categoría (opcional)» con «Sin categoría» como primera opción y ya no pide crear una categoría primero. El listado deja de mostrar «Crear categoría» en el encabezado y en el estado vacío: siempre ofrece «Nuevo producto». La columna Categoría muestra «—» cuando no hay. El estado vacío de la tabla de productos usa un ícono de producto (`emptyIcon` en `DataTable`).

3. **Verificar el funcionamiento.** Pruebas en MySQL (`miventa_test`): crear, listar y editar productos sin categoría, y mantener el rechazo de categorías de otro negocio, inexistentes o inactivas.

4. **Documentar y entregar.** Guardar este plan y su caso de uso en `docs/` y actualizar los casos de uso de productos que mencionaban la categoría obligatoria.

## Verificación

- Un producto se crea y se edita sin categoría; se puede quitar la categoría de un producto existente.
- Una categoría de otro negocio, inexistente o inactiva sigue rechazándose con `422`.
- Sin categorías creadas, Productos solo ofrece «Nuevo producto».
