# 09-productos-venta-e-insumos

1. **Base de datos y API.** Dos columnas nuevas en `productos`: `se_vende` (por defecto sí) e `es_insumo` (por defecto no); al menos una debe estar activa. `precio` pasa a ser nullable y es obligatorio solo si el producto se vende. Se amplía el enum `UnidadVenta` con `g`, `ml`, `l` y `m`, además de `unidad` y `kg`. Los productos existentes quedan como «se vende». El listado admite el filtro `tipo` (`venta` o `insumo`) y la API devuelve ambos indicadores. La columna `unidad_venta` conserva su nombre.

2. **Pantallas de Productos.** El formulario tiene los interruptores «Se vende» y «Es insumo», el precio es opcional si el producto no se vende y el selector «Unidad de medida» incluye las unidades nuevas. El listado tiene la columna y el filtro «Tipo», y muestra «—» cuando no hay precio. El módulo «Venta por peso» pasa a llamarse «Venta por medida» (peso, litro y metro) en el plan Comercio.

3. **Verificar el funcionamiento.** Pruebas en MySQL (`miventa_test`): crear insumos, productos de venta y ambos; rechazar un producto sin ningún uso; precio obligatorio solo si se vende; filtro por tipo; unidades nuevas; y que los productos existentes sigan como estaban.

4. **Documentar y entregar.** Guardar este plan y sus casos de uso en `docs/`, actualizar el caso de uso de productos y hacer el commit. Las recetas y el descuento de stock al vender quedan fuera de este plan.

## Verificación

- Un insumo se guarda sin precio; un producto que se vende sin precio se rechaza con `422`.
- Un producto sin «se vende» ni «es insumo» se rechaza con `422`.
- Las unidades `unidad`, `kg`, `g`, `l`, `ml` y `m` se aceptan; cualquier otra se rechaza.
- El filtro por tipo devuelve los productos de venta o los insumos; un producto marcado como ambos aparece en los dos.
