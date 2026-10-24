1. **Agregar Administración → Mi negocio.** Acceso mediante el permiso `gestionar_negocio`, con carga, errores y reintentos coherentes con el sistema. Usar una página de configuración organizada por secciones.

2. **Editar los datos del negocio.** Nombre comercial, RUT, razón social, giro, dirección, teléfono y correo. Validar y normalizar el RUT como en MiVenta original. Mostrar el código de negocio como dato fijo, porque se utiliza para iniciar sesión. Al guardar el nombre, actualizarlo también en la cabecera y el menú.

3. **Mostrar el plan y administrar sus módulos.** Mostrar el plan contratado y permitir activar o desactivar únicamente los módulos incluidos. Explicar cuáles no están disponibles en ese plan. Desactivar un módulo conservará sus datos; el cambio de plan se gestionará posteriormente desde Mi plan.

4. **Configurar las preferencias de operación.** Incorporar cierre ciego de caja y descuento máximo permitido al cajero, entre 0 % y 100 %. Guardar estas preferencias como configuración del negocio; sus efectos se integrarán al implementar turnos de caja y ventas.

5. **Guardar cada sección de forma independiente.** Datos, módulos y preferencias tendrán su propio guardado, validación e indicador de progreso. Conservar lo escrito ante errores, impedir envíos duplicados y validar permisos y aislamiento entre negocios desde el servidor.

6. **Documentar, verificar y entregar.** Guardar este plan en `docs/03-mi-negocio.md`. Ejecutar las pruebas afectadas en MySQL, compilar y revisar escritorio, móviles y tablets con el navegador. Al terminar, hacer el commit y avisar al usuario para el push.
