# 07-PLAN-mejoras-mantenedores-catalogo

1. **Precio de producto.** Un precio vacío debe producir un error de validación en el formulario y no convertirse en cero. El valor `0` ingresado expresamente sigue permitido.

2. **Tipos de entrada.** Productos, Categorías y Marcas deben recortar espacios de valores de texto sin convertir arreglos u otros tipos incorrectos a cadenas; el servidor debe rechazarlos con errores de validación.

3. **Opciones independientes.** Cargar las opciones de categorías y marcas por separado del listado paginado de productos. Buscar, filtrar o cambiar de página no debe vaciar los selectores del formulario. Mostrar carga, error y reintento cuando las opciones no estén disponibles.

4. **Primer producto y entrega.** Si no existe una categoría activa, guiar al usuario a crearla antes de crear un producto. Mantener el flujo de edición acordado, documentar los casos de uso, probar el comportamiento y revisar la interfaz en escritorio y tamaños de iPhone, Android y tablets. Al terminar, hacer el commit y avisar para el push.

## Verificación

- Un precio vacío se rechaza; un cero explícito se conserva.
- Los tipos incorrectos se rechazan en creación y edición.
- Las opciones del formulario se mantienen durante la recarga del listado y permiten reintentar si fallan.
- Sin categoría activa se muestra el camino para crearla.
