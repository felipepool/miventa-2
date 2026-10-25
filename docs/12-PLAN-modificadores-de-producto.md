# 12-modificadores-de-producto

1. **Base de datos y API.** Tres tablas aisladas por negocio: `grupos_modificadores` (nombre, referencia opcional, tipo de selección, mínimo, máximo, unidades gratis, precio extra y estado), `modificadores` (nombre, precio adicional, cantidad máxima, orden y estado) y `producto_grupo_modificador` (producto, grupo y orden). Se exponen por la API versionada con el permiso `gestionar_catalogo`: listado paginado de grupos con búsqueda y filtro por estado, detalle, creación y edición del grupo junto con sus modificadores, y activación o desactivación del grupo. Como en el resto del catálogo, no se elimina nada: se desactiva. Las rutas exigen que el módulo Modificadores esté activo en el negocio; el módulo es gratuito y viene incluido en los planes Gastronomía y Mixto.

2. **Reglas de grupos y modificadores.** Cada grupo elige el tipo de selección: única (una sola opción), múltiple (varias opciones, cada una sí o no) o cantidad (varias unidades de cada opción). Define un mínimo y un máximo de unidades, y puede tener unidades gratis con un precio extra por cada unidad que las supere. Cada modificador puede ser sin costo (`precio_adicional` en 0) o con un costo adicional que define el usuario, en pesos enteros con IVA incluido. Los modificadores que se quitan al editar el grupo se desactivan, no se borran. El grupo debe tener al menos un modificador. Un grupo o modificador desactivado no se puede asignar ni elegir en productos nuevos, pero lo conservan los productos que ya lo usan.

3. **Asignación en el formulario del producto y pantallas.** En Catálogo se agrega el submenú Modificadores, visible con `gestionar_catalogo` y el módulo activo, con listado, búsqueda y filtros, y formulario en diálogo siguiendo el flujo de Usuarios (el grupo se edita con sus modificadores dentro del mismo formulario). La asignación se hace solo en el formulario del producto: una sección «Grupos de modificadores» donde se eligen los grupos activos del negocio y se define su orden. Con el módulo apagado la sección y el submenú se ocultan, y las asignaciones existentes se conservan.

4. **Verificar y entregar.** Pruebas en MySQL (`miventa_test`) de los casos válidos, inválidos, no autorizados, entre negocios y con el módulo apagado; revisión de las pantallas en tu Chrome en escritorio, tablet y celular; documentación del plan y sus casos de uso y commit. Quedan fuera: elegir modificadores y cobrarlos en la venta (plan de ventas), su efecto en insumos y recetas, imágenes y la importación o exportación por CSV.

## Verificación

- Un grupo se crea y se edita con sus modificadores; los modificadores quitados quedan inactivos y no se borran.
- Se rechazan reglas incoherentes (mínimo mayor que el máximo, selección única con máximo mayor que 1, precios fuera de rango, grupo sin modificadores).
- La asignación en el producto respeta el orden, rechaza grupos de otro negocio o inactivos y conserva los ya asignados.
- Sin el módulo activo, la API responde `404`; los datos y las asignaciones se conservan.
