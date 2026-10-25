# 11-inventario-adicional-contratable

1. **Catálogo y precio.** `Inventario` se suma a los adicionales (`ConceptoTarifa`), junto a facturación electrónica, gestión de mesas y KDS, con una tarifa de 0,5 UF mensuales + IVA en los planes Comercio, Gastronomía y Mixto, creada por una migración. En Mi plan se muestra y se contrata como los otros adicionales, con el desglose mensual, la aceptación del cobro y la foto del precio aceptado: cambiar la tarifa después no afecta a quien ya contrató.

2. **Estado y reglas de contratación.** Durante la prueba de 14 días el inventario está habilitado, igual que los demás adicionales. Al vencer la prueba solo queda activo si fue contratado. Si se deja de contratar, el stock y el historial se conservan y vuelven a estar disponibles al contratarlo de nuevo.

3. **Aplicación en servidor e interfaz.** Un middleware `adicional:inventario` exige el adicional activo en todas las rutas `/api/v1/inventario`; sin él responde `403` con `code: adicional_no_contratado`. El ítem «Inventario» del menú aparece solo si el adicional está activo y el usuario tiene el permiso `gestionar_inventario`. Quien entra por URL sin el adicional ve una pantalla que explica que no está contratado: el administrador tiene un enlace a Mi plan y los demás usuarios un aviso para consultar con él. El mecanismo queda reutilizable para mesas, KDS y facturación.

4. **Verificar y entregar.** Pruebas en MySQL (`miventa_test`): inventario durante la prueba, contratado, no contratado, prueba vencida y contrato cerrado; conservación de datos al dejar y volver a contratar; cotización y foto de precio; permisos y aislamiento entre negocios. Revisión de las pantallas en escritorio, tablets y celulares. Guardar este plan y sus casos de uso en `docs/`, ajustar los casos de uso del plan 10 y hacer el commit. Quedan fuera: el cobro automático y la pasarela de pago, y condicionar mesas, KDS y facturación, que aún no existen.

## Verificación

- En prueba, el inventario responde sin contratación; vencida la prueba, sin contrato responde `403` con `adicional_no_contratado`.
- Contratar el adicional desde Mi plan lo habilita y conserva el precio aceptado aunque cambie la tarifa.
- Dejar de contratarlo lo bloquea sin borrar stock ni historial, que reaparecen al contratarlo otra vez.
- Un negocio con el adicional no habilita a otro; el adicional no reemplaza el permiso `gestionar_inventario`.
