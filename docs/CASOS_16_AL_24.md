# Implementación de casos de uso 16 al 24

Se completó el flujo de pedidos, cobros, reportes y promociones usando el método de los casos anteriores: controladores Laravel, modelos Eloquent, vistas Blade, validación en servidor y rutas con permisos. La migración necesaria ya fue aplicada a MySQL. Los datos de prueba se revirtieron.

## Cambios por caso

| Caso | Carpeta y archivos | Cambios realizados |
| --- | --- | --- |
| CU16 Registrar pedido | app/Http/Controllers/PedidoController.php; app/Services/PedidoService.php; resources/views/pedidos/form.blade.php; public/js-borradores.js | Se conserva el registro de productos, cantidades, opciones, fecha del servidor, usuario autenticado y estado Registrado. Se centraliza el cálculo de precios en centavos y se agrega selección de una promoción vigente, subtotal, descuento y total estimado. El servidor verifica stock y recalcula todos los importes. Los borradores incluyen la promoción y se eliminan solo al confirmar el guardado. |
| CU17 Modificar o cancelar pedido | PedidoController.php; resources/views/pedidos/form.blade.php y show.blade.php | Se agrega formulario de modificación que carga los detalles existentes. Recalcula precios y descuentos y reemplaza los detalles dentro de una transacción. Solo admite pedidos Registrados sin venta. Cancelar cambia el estado a Cancelado y conserva el pedido y detalles; no borra registros. Borrador de edición separado del borrador de pedido nuevo. |
| CU18 Cambiar estado del pedido | PedidoController.php; resources/views/pedidos/show.blade.php | Se permite Pagado a Completado al entregar. Registrado a Pagado se realiza al registrar un cobro en CU19. Registrado a Cancelado se realiza con CU17. Se rechazan saltos, retrocesos y completar sin venta. |
| CU19 Registrar venta y pago | app/Http/Controllers/VentaController.php; app/Models/Venta.php; app/Models/Pedido.php; resources/views/ventas/index.blade.php y form.blade.php | Pantalla de pendientes, detalle del cobro y últimas ventas. Efectivo calcula cambio y rechaza monto insuficiente. QR registra el pago confirmado por el cajero con cambio cero, sin integración bancaria. Total se obtiene de los detalles y descuento guardados, no del cliente. Venta, salidas de inventario, actualización de stock y estado Pagado se guardan en una transacción. Bloqueo del pedido e índice único impiden cobros duplicados. |
| CU20 Historial de pedidos | PedidoController.php; resources/views/pedidos/index.blade.php y show.blade.php | Se reemplaza la salida JSON por una pantalla con filtros por fecha y estado, paginación, mensaje de lista vacía y detalle de productos, descuento y pago. Administrador y mesero acceden según consultar_historial_pedidos. Cajero puede abrir el detalle con cobrar_ventas. |
| CU21 Ventas diarias | app/Http/Controllers/ReportesController.php; resources/views/reportes/diarias.blade.php | Filtro por fecha, total de ventas Completadas y cantidad de pedidos cobrados. Muestra cada venta y mensaje Sin ventas registradas. Valida el formato de fecha. |
| CU22 Reporte por método de pago | ReportesController.php; resources/views/reportes/pagos.blade.php | Rango de fechas validado, agrupación por método con cantidad y total, más total general. QR y Transferencia QR de datos históricos se agrupan como QR. Otros métodos históricos se conservan y muestran para no excluir ventas existentes. Nuevos cobros admiten Efectivo o QR. |
| CU23 Reporte de inventario | ReportesController.php; resources/views/reportes/inventario.blade.php | Selector entre inventario actual y movimientos; filtro por producto. Los movimientos admiten rango de fechas y paginación, mostrando cantidad o diferencia, motivo y usuario. Inventario actual muestra existencia, mínimo y última actualización. |
| CU24 Promociones y descuentos | app/Http/Controllers/PromocionesController.php; app/Models/Promocion.php; app/Services/PedidoService.php; resources/views/promociones/index.blade.php y form.blade.php | Formularios para crear y editar; acciones para activar y desactivar conservando historial. Porcentaje hasta 100% o monto fijo en Bs, aplicado a un producto específico o a todo el pedido. Fechas, intervalo horario opcional y tope de descuento. Rechaza vigencias inválidas y advierte si el producto no tiene stock. Solo promociones activas, configuradas y vigentes se pueden aplicar a un pedido. |

## Archivos compartidos y base de datos

- routes/web.php: rutas de edición, cancelación, estado, cobro, activación de promociones y tres reportes. Cada escritura requiere el permiso correspondiente. El detalle del pedido permite los permisos de pedidos, historial o cobro desde el controlador.
- resources/views/layouts/app.blade.php: enlaces al historial, ventas y cobros, reportes y promociones, visibles según permisos.
- app/Models/Pedido.php: campos subtotal, descuento y promocion_id; relaciones con promoción y venta.
- app/Models/Venta.php: relaciones con pedido y usuario.
- app/Models/Promocion.php: relación con producto, comprobación de vigencia y cálculo de descuento limitado al importe elegible.
- public/js-borradores.js: guarda promoción; al descartar una edición vuelve al detalle usando una ruta GET.
- database/migrations/2026_10_06_000002_completar_pedidos_ventas_promociones.php: índice único de ventas por pedido; producto_id opcional en promociones; subtotal, descuento y promocion_id en pedidos. Inicializa subtotal de pedidos existentes con su total. No borra registros. La migración fue aplicada correctamente a MySQL.
- tests/Feature/InventarioPedidosTest.php: se amplían las pruebas aisladas para pedidos, cobros, estados, reportes y promociones.

## Uso y reglas

1. Mesero: Nuevo pedido, seleccionar productos y promoción opcional, confirmar. Desde Historial puede consultar, modificar o cancelar un pedido Registrado.
2. Cajero: Ventas y cobros, elegir un pedido pendiente, confirmar Efectivo o QR y registrar. Se descuenta inventario automáticamente una vez por cobro.
3. Mesero: abrir el detalle del pedido Pagado y marcar como Completado después de entregarlo.
4. Administrador: consultar los tres reportes y gestionar promociones.

El registro y modificación del pedido no reservan stock. Las existencias se comprueban nuevamente y se descuentan al cobrar. No registrar otra salida manual por la misma venta, porque CU19 ya genera el movimiento. Una salida manual corresponde a otra operación, como merma.

Se permite una promoción por pedido. Si es de producto, aplica al importe conjunto de ese producto; si es de pedido, aplica al subtotal completo. El descuento fijo se aplica una sola vez y nunca hace negativo el total. El tope limita el descuento. Los detalles conservan precio y subtotal antes del descuento; el pedido guarda el descuento y total finales. Cambiar o desactivar una promoción no modifica importes de pedidos ya registrados; editar un pedido recalcula según los precios y vigencia actuales.

Promociones antiguas con tipos como 2x1 o con alcance Categoría se conservan, pero requieren edición a los tipos y alcances contemplados en CU24 antes de aplicarse. No se interpreta automáticamente un tipo ambiguo ni se modifican promociones existentes.

Las fechas usan la zona horaria configurada por la aplicación. Para franjas horarias se exige inicio y fin en el mismo día, o dejar ambos vacíos para toda la jornada.

Los permisos utilizados ya existían: registrar_pedidos, modificar_pedidos, cambiar_estado_pedidos, cobrar_ventas, consultar_historial_pedidos, ver_reportes y gestion_promociones. Se conservan las asignaciones actuales; si no aparece una opción, revisar Roles y permisos con el administrador.

## Validación

- 11 pruebas PHPUnit aprobadas con 146 aserciones, usando SQLite temporal en memoria.
- Registro, modificación, cancelación, transiciones inválidas, efectivo insuficiente, cálculo del cambio, QR, rechazo de doble cobro, falta de stock y bloqueo de pedidos pagados.
- Porcentaje inválido, fechas y horas inválidas, tope, promoción por producto, promoción inactiva, filtros, reportes y permisos.
- Vistas Blade compiladas y rutas de reportes verificadas.
- Flujo completo comprobado en MySQL real: promoción, pedido, modificación, QR, descuento de stock, estado Completado y renderizado de detalle, reportes y promociones. Se revirtió la transacción de prueba; no quedaron pedidos, ventas o cambios de existencias de esa comprobación.
- Sin revisión visual interactiva del navegador. PHP conserva su advertencia preexistente de extensión zip; no impidió migraciones ni pruebas.

## Corrección posterior desde caja

El cajero ahora puede corregir un pedido Registrado y sin cobrar con su permiso cobrar_ventas. Se muestra el stock antes del pago y se bloquea un cobro cuya pantalla tenga una versión anterior del pedido. Véase docs/CORRECCION_PEDIDOS_CAJA.md para los archivos, el flujo y la validación.
