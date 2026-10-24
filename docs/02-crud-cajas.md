1. **Listado ordenado por sucursal.** Mantener el CRUD independiente en Administración → Cajas. Mostrar sucursal, nombre, estado y si la caja está incluida o es adicional. Agregar búsqueda, filtros por sucursal y estado, y acciones compactas iguales a Usuarios.

2. **Crear y editar.** Al crear, elegir una sucursal activa y escribir el nombre. Evitar nombres repetidos dentro de esa sucursal. Al editar, permitir renombrar; la sucursal permanecerá fija para conservar su historial. Usar `EditDialog` y `FormDialog`, con los mismos estados de carga, error y reintento.

3. **Activar y desactivar.** Conservar los datos mediante desactivación. Impedir crear o activar cajas en sucursales inactivas. Mostrar una confirmación clara con el efecto sobre su disponibilidad y cobro.

4. **Aplicar la caja incluida y los adicionales.** Cada sucursal tendrá una caja activa incluida; las restantes costarán 0,25 UF/mes + IVA, según la tarifa editable. Mostrar cobro, prorrateo y aceptación antes de crear o reactivar una adicional. Guardar el precio contratado y conservar su historial. Si se desactiva la caja incluida, otra activa ocupará ese lugar, para que nunca se cobre una caja adicional cuando solo queda una activa. La caja incluida conservará su condición mientras siga activa.

5. **Proteger permisos y cobros.** Usar el permiso `gestionar_cajas`, aislar los negocios y validar todo desde el servidor. Coordinar los cambios con la activación y desactivación de sucursales, evitando contratos duplicados y errores por operaciones simultáneas.

6. **Verificar y entregar.** Ejecutar pruebas en MySQL y revisar escritorio, iPhone, Android y tablets mediante los tamaños de navegador disponibles. Actualizar el negocio demo con cajas adicionales para probar el listado. Terminar con commit para que el usuario haga el push.
