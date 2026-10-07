# Corrección de pedidos desde caja por falta de stock

El cajero puede corregir un pedido Registrado y sin cobrar cuando el stock cambió o el cliente solicita una alternativa. No necesita esperar al mesero. Guardar recalcula el total y vuelve a la pantalla de cobro; después confirma el nuevo importe con el cliente y registra el pago.

## Flujo

1. Ventas y cobros → Cobrar: se muestran productos, cantidades solicitadas y stock actual.
2. Si hay falta de disponibilidad, se bloquea Registrar pago y se ofrece Corregir pedido.
3. El cajero ajusta cantidades, quita líneas o cambia productos, conforme a lo acordado con el cliente.
4. Guardar cambios actualiza pedido, detalles, descuento y total en una transacción, sin descontar stock todavía.
5. Al volver al cobro se muestra el importe nuevo. Al registrar el pago se descuenta stock y se calcula el cambio.

Ejemplo comprobado: dos bebidas de Bs 15 suman Bs 30. Si queda una, el cajero conserva una y agrega una alternativa de Bs 8. El pedido pasa a Bs 23; si recibe Bs 30, el cambio es Bs 7.

## Carpetas y archivos modificados

| Archivo | Cambio |
| --- | --- |
| routes/web.php | GET y PUT /admin/ventas/corregir-pedido/{id}, protegidos por auth y cobrar_ventas. |
| app/Http/Controllers/PedidoController.php | Reutiliza edición y validación existentes para caja y vuelve al cobro tras guardar. Conserva el mesero autor del pedido. Solo admite estado Registrado sin venta. |
| resources/views/pedidos/form.blade.php | Envía correcciones de caja a la ruta autorizada para el cajero y muestra existencias de cada producto. |
| app/Http/Controllers/VentaController.php | Carga stock y categoría, identifica falta de disponibilidad antes de cobrar y rechaza el pago si el pedido cambió desde que se abrió la pantalla. |
| app/Models/Pedido.php | versionCobro calcula una firma del estado, importe, descuento y detalles para detectar cambios. |
| resources/views/ventas/form.blade.php | Detalle con stock, aviso de conflicto, botón Corregir pedido, pago deshabilitado si falta disponibilidad y firma de la versión del pedido. Limpia el borrador de edición después de guardar. |
| resources/views/pedidos/show.blade.php | Acceso Corregir pedido en caja para usuarios con cobrar_ventas, solo en pedidos sin pagar. |
| tests/Feature/InventarioPedidosTest.php | Reproduce el stock de una unidad, rechazo de un pedido de dos, sustitución desde caja, total nuevo, cobro obsoleto rechazado y bloqueo de edición tras el pago. |

No se amplían las asignaciones de roles ni se requiere migración. El permiso cobrar_ventas autoriza este flujo de corrección; las rutas de edición habituales del mesero mantienen modificar_pedidos. Los cambios quedan auditados con el usuario del cajero.

La validación de stock ya ocurre al registrar y modificar el pedido, antes de cualquier pago. La prueba confirma que un pedido de dos unidades se rechaza cuando el stock real es una. El sistema no reserva existencias de pedidos pendientes, así que otro cobro o movimiento puede reducirlas después del registro. Por eso se verifica nuevamente al abrir y confirmar el cobro. Esta corrección no agrega reserva de stock.

Validación: 15 pruebas y 203 aserciones aprobadas con base temporal. No se alteraron las existencias ni se registraron cobros de prueba en MySQL real.
