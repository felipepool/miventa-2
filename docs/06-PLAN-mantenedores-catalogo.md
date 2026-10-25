# 06-PLAN-mantenedores-catalogo

1. **Marcas y categorías.** Crear las tablas aisladas por negocio, con identificador público (ULID), nombre único dentro del negocio (sin distinguir mayúsculas) y estado activa/inactiva. Exponerlas por la API versionada con el permiso `gestionar_catalogo`: listado paginado con búsqueda y filtro por estado, detalle, creación, edición y activación/desactivación. Igual que Usuarios, no se eliminan: se desactivan para conservar el historial. Desactivar una marca o categoría no modifica los productos que ya la usan, pero no podrá asignarse a productos nuevos ni a productos que no la tuvieran.

2. **Productos.** Crear la tabla de productos del negocio con categoría obligatoria, marca opcional, SKU, código de barras, nombre, descripción, unidad de venta (unidad o kilo), precio con IVA incluido en pesos enteros, costo opcional y si controla stock. SKU y código de barras son únicos por negocio. Exponerlos por la API con listado paginado, búsqueda por nombre, SKU o código de barras, filtros por categoría, marca y estado, detalle, creación, edición y activación/desactivación, sin eliminación. Un producto puede conservar una categoría o marca que luego se desactivó mientras no se cambie. Quedan fuera de este plan: tipo venta/insumo y recetas, imagen del producto, importación y exportación por CSV, modificadores, stock por sucursal e inventario.

3. **Pantallas.** Agregar un grupo **Catálogo** al menú lateral, separado de Administración y visible solo con `gestionar_catalogo`. Dentro de Catálogo, mostrar Productos, Categorías y Marcas como submenús; expandir el grupo cuando se visite cualquiera de esas pantallas. Seguir el flujo de Usuarios: listado con búsqueda y filtros en la URL, `FormDialog` para crear, `EditDialog` con diálogo de carga inicial para editar y `ConfirmModal` para activar o desactivar, con los mismos estados de carga, error, reintento y registro no disponible. Las pantallas deben adaptarse a escritorio, tablets y smartphones.

4. **Seguridad y entrega.** Validar y autorizar en el servidor: pertenencia al negocio en cada operación, registros de otro negocio responden 404, la categoría y la marca de un producto deben ser del mismo negocio, el cliente no puede fijar `negocio_id` ni el estado al crear, y el negocio en solo lectura bloquea las modificaciones. Evitar duplicados por solicitudes simultáneas con restricciones únicas en la base y bloqueo del negocio. Documentar los casos de uso en archivos independientes, probar en MySQL (`miventa_test`) los casos válidos, inválidos, no autorizados, entre negocios y concurrentes; revisar las pantallas en escritorio y tamaños de iPhone, Android, iPad y otras tablets, distinguiendo emulación de pruebas en dispositivos reales. Al terminar, hacer el commit y avisar para el push.

## Verificación

- Marcas, categorías y productos tienen CRUD sin eliminación; se desactivan para conservar su historial.
- Catálogo aparece como grupo separado del menú Administración y contiene Productos, Categorías y Marcas.
- Los formularios y diálogos siguen los patrones de Usuarios.
- Los endpoints rechazan accesos sin permiso, registros ajenos, relaciones de otros negocios y duplicados.
- Las pruebas MySQL y la revisión responsive cubren los comportamientos acordados.
