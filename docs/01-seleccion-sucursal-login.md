1. **Revisar la base actual y MiVenta original.** Aprovechar el contexto de sucursal, el selector de cabecera y los permisos que ya tenemos.

2. **Mantener el login actual.** Primero ingresar código de negocio, usuario y contraseña. Después de validar las credenciales, resolver las sucursales activas a las que tiene acceso el usuario:
   - **Una disponible:** entrar directamente.
   - **Varias disponibles:** mostrar «¿En qué sucursal trabajarás?».
   - **Ninguna disponible:** mostrar un mensaje claro para contactar al administrador y permitir cerrar sesión.

3. **Crear una pantalla de selección simple.** Mostrar nombre y dirección, cuando exista, con una única selección y botón **«Ingresar a esta sucursal»**. Preseleccionar la última sucursal utilizada si continúa activa y autorizada para ese usuario; si no hay una recordada válida, preseleccionar la sucursal con `es_principal = true` siempre que esté activa y autorizada. Si ninguna cumple, dejar la selección vacía. Pedir confirmación en cada nuevo inicio de sesión cuando haya varias sucursales, aunque una esté preseleccionada.

4. **Aplicar los permisos desde el servidor.** El administrador podrá elegir cualquier sucursal activa de su negocio; los demás usuarios, solo las que tengan asignadas. Volver a validar el acceso al confirmar, por si la sucursal fue desactivada o cambió la asignación.

5. **Completar el ingreso con un contexto válido.** Hasta elegir sucursal, el usuario permanecerá en esa pantalla, incluso si intenta abrir otra página directamente. Después verá la sucursal elegida en la cabecera y podrá cambiarla mediante el selector existente. Recargar la página conservará la selección.

6. **Verificar y entregar.** Probar los tres escenarios, acceso entre negocios, pérdida de permisos y conservación de la selección. Revisar escritorio, móviles y tablets; al terminar, hacer el commit y avisar al usuario para el push.
