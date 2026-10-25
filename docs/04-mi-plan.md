1. **Agregar Administración → Mi plan.** Acceso mediante el permiso `gestionar_negocio`, con carga, errores y reintentos coherentes con el sistema. Agregar en Mi negocio un enlace a esta página.

2. **Mostrar el plan contratado.** Nombre, descripción, funcionalidades incluidas y precio mensual contratado en UF + IVA, con la fecha desde la que rige.

3. **Detallar el cobro mensual.** Listar el plan, las sucursales adicionales y las cajas adicionales vigentes, cada una con su precio contratado, y sumar el total mensual en UF + IVA. Indicar que cada sucursal incluye una caja y que la sucursal principal no tiene cobro adicional.

4. **Cambiar de plan.** Permitir elegir entre Comercio y Gastronomía; Mixto se mantiene solo para quien ya lo tiene. Antes de confirmar, mostrar el nuevo precio, los módulos que se agregan y los que dejan de estar disponibles (sus datos se conservan), y exigir que se acepte el cobro. Al confirmar se cierra el contrato anterior, comienza el nuevo y se activan los módulos del plan elegido. Las sucursales y cajas adicionales conservan su precio contratado.

5. **Proteger el cambio.** Validar permisos y aislamiento entre negocios desde el servidor, rechazar un precio aceptado que ya no coincida con la tarifa vigente y evitar contratos duplicados ante envíos repetidos u operaciones simultáneas. Actualizar el menú y la cabecera después del cambio.

6. **Documentar, verificar y entregar.** Guardar este plan en `docs/04-mi-plan.md`. Ejecutar las pruebas afectadas en MySQL, compilar y revisar escritorio, iPhone, Android, iPad y tablets con el navegador. Al terminar, hacer el commit y avisar al usuario para el push.
