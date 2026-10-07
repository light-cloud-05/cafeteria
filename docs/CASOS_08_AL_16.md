# Implementación de casos de uso 8 al 16

Se mantiene el método de CU01–CU07: rutas web autenticadas, permisos existentes, controladores con validación, modelos Eloquent y vistas Blade que extienden layouts.app.

| Caso | Carpeta y archivos | Cambios |
| --- | --- | --- |
| CU08 Gestionar proveedores | app/Http/Controllers/ProveedorController.php | Se conserva crear, editar, listar y desactivar. Se agrega validación del formato del teléfono y mensaje en español. Las vistas existentes resources/views/proveedores ya muestran errores. |
| CU09 Entrada | app/Http/Controllers/MovimientoInventarioController.php; app/Services/InventarioService.php; resources/views/inventario/form.blade.php | Cantidad positiva, producto existente, proveedor activo opcional, motivo y fecha. Transacción y bloqueo del producto; incremento de stock y movimiento indivisibles. Borrador local ante recarga o fallo de conexión. |
| CU10 Salida | Mismos archivos de CU09 | Permiso específico para salida, cantidad positiva, rechazo de producto desactivado y stock insuficiente. Se descuenta stock sin permitir negativos. |
| CU11 Ajuste | Mismos archivos de CU09 | Recibe conteo físico no negativo, muestra diferencia estimada, guarda diferencia real calculada bajo bloqueo. Sin movimiento si la diferencia es cero. |
| CU12 Stock actual | app/Http/Controllers/StocksController.php; app/Models/Stock.php; resources/views/inventario/stock.blade.php | Lista productos y existencias con filtro de categoría. Incluye productos sin stock con indicador explícito. Se agrega relación stock-producto. |
| CU13 Stock mínimo | StocksController.php; resources/views/inventario/stock.blade.php | Lista stock actual menor o igual al mínimo; resalta necesidad de reposición. Permite definir el mínimo con permiso registrar_ajustes. |
| CU14 Buscar producto | app/Http/Controllers/CatalogoController.php; resources/views/catalogo/index.blade.php | Búsqueda por nombre o ID numérico usado como código. Campo vacío lista todos; resultado vacío muestra mensaje; selección muestra el producto. |
| CU15 Aviso de agotado | app/Services/InventarioService.php; app/Models/Producto.php; StocksController.php; resources/views/layouts/app.blade.php | Estado efectivo Agotado cuando stock <= mínimo, bloqueo al agregar pedidos, aviso visible para usuarios con ver_agotados y listado de reposición. Bitácora al pasar a agotado o reponerse; no repite eventos mientras permanece agotado. Reposición habilita el producto si está activo y su categoría también. |
| CU16 Registrar pedido | app/Http/Controllers/PedidoController.php; app/Models/Pedido.php; app/Models/DetallePedido.php; resources/views/pedidos/form.blade.php y show.blade.php | Formulario con líneas, cantidades, indicaciones y total estimado. Total y precio se recalculan en servidor usando centavos; fecha y usuario son del servidor. Estado Registrado. Pedido y detalles se guardan juntos; rechaza líneas duplicadas, cantidades inválidas, inactivos, agotados y stock insuficiente. Detalle de confirmación y borrador local. |

## Archivos compartidos

- routes/web.php: rutas para entradas, salidas, ajustes, stock mínimo, avisos, catálogo, nuevo pedido, confirmación y definición de mínimos. Permisos individuales; el controlador valida permiso por tipo de movimiento.
- resources/views/layouts/app.blade.php: enlaces según permisos, aviso de reposición y carga del script de borradores.
- public/js-borradores.js: guarda datos en localStorage por usuario y formulario; excluye token CSRF; restaura después de recargar y permite descartar. El pedido elimina el borrador al mostrar su confirmación.
- app/Models/Producto.php: relación stock y disponibilidad efectiva, conservando la desactivación comercial manual.
- app/Models/MovimientoInventario.php: relaciones con producto, proveedor y usuario.
- resources/views/inventario/movimientos.blade.php: historial paginado de movimientos.
- database/migrations/2026_10_06_000001_preparar_inventario.php: índice único para un stock por producto y proveedor nullable para movimientos sin proveedor. Si hay stocks duplicados detiene la migración para revisión, sin borrar datos.
- tests/Feature/InventarioPedidosTest.php: pruebas aisladas con SQLite en memoria; no modifican la base del proyecto.

## Decisiones y límites del informe

El esquema existente no tiene estado pendiente para movimientos. Todos los movimientos de esta implementación se registran y aplican en una transacción, y los borradores quedan en el navegador; por eso no existe un movimiento pendiente persistido que permita aplicar la excepción de CU08 sobre desactivación de proveedores. No se bloquea la desactivación por movimientos históricos ya aplicados.

Los avisos de CU15 son dentro del sistema al abrir o recargar las pantallas, para las cuentas con el permiso ver_agotados. No se agrega correo, mensajería externa ni actualización en tiempo real. Un mismo agotamiento conserva su aviso visible, pero solo registra una transición en bitácora. La condición de stock se mantiene separada del campo disponible para que una reposición no reactive un producto desactivado manualmente.

CU16 comprueba existencias al confirmar, pero no reserva ni descuenta inventario. El descuento corresponde a la salida de CU10; el informe no exige descontar al registrar el pedido. CU17–CU25 no se implementan en esta entrega. Por tanto, dos pedidos pendientes pueden solicitar las mismas existencias; un futuro cobro o reserva deberá volver a validar el stock.

## Activación de la base de datos

La conexión configurada a MySQL en 127.0.0.1:3308 rechaza la conexión. La migración nueva todavía no pudo aplicarse a la base real. Iniciar MySQL con la configuración actual y ejecutar desde esta carpeta:

```powershell
C:\xampp\php\php.exe artisan migrate
```

Los permisos ya están definidos en database/seeders/PermisoSeeder.php. Revisar en Roles y permisos que cada cuenta tenga los correspondientes. No se modifican asignaciones existentes automáticamente.

## Validación

- PHP sin errores de sintaxis en controladores, modelos, servicio y migraciones.
- Rutas registradas verificadas con artisan route:list.
- Vistas Blade compiladas con artisan view:cache.
- PHPUnit: versión final aprobada con 4 pruebas y 36 aserciones.
- Pendiente validación contra MySQL real y comprobación visual interactiva del navegador. PHP muestra una advertencia preexistente sobre la extensión zip que no impide estas comprobaciones.


## Corrección de definición de stock actual

En resources/views/inventario/stock.blade.php se añadió un formulario por producto para ingresar su stock actual y motivo, visible con permiso registrar_ajustes. Usa la ruta existente de ajustes. MovimientoInventarioController.php permite volver a la consulta de stock tras guardar. La cantidad sustituye la existencia actual; el historial guarda solo la diferencia. También crea el registro de stock si el producto aún no tiene uno. Se conservan las validaciones y la transacción.

Verificación de esta corrección: 5 pruebas y 49 aserciones aprobadas, incluyendo actualización desde la consulta, rechazo de negativos y definición inicial de un producto sin registro de stock. No se requiere una migración adicional a la ya documentada.

## Migración aplicada en MySQL

Se aplicó correctamente 2026_10_06_000001_preparar_inventario a la base real. Se comprobó que movimiento_inventarios.proveedor_id acepta NULL y que un ajuste sin proveedor permite actualizar el stock. La comprobación se ejecutó en una transacción revertida, sin conservar cambios de inventario. La indicación anterior de migración pendiente queda resuelta.

## Continuación de casos 16 al 24

La implementación posterior completa pedidos, cobros, reportes y promociones. Consulte docs/CASOS_16_AL_24.md. El descuento de stock ahora se realiza automáticamente al cobrar en CU19; no se debe registrar otra salida manual por esa misma venta. La limitación anterior de ausencia de cobros queda superada por esta continuación.
