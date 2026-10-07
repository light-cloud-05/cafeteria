# Implementación del caso de uso 25 y verificación de conexiones

CU25 Consultar Bitácora del Sistema quedó completado con consulta de eventos, filtros de fecha, usuario y acción, dirección IP y detalle de los valores anteriores y nuevos. Se mantiene el método del proyecto: controlador, modelo Eloquent, vistas Blade, rutas autenticadas y permiso consultar_bitacora.

## Cambios por archivo y carpeta

| Carpeta | Archivo | Cambio |
| --- | --- | --- |
| app/Http/Controllers | BitacorasController.php | Mantiene filtros por nombre de usuario exacto y acción; añade fechas desde y hasta, validación del rango, paginación de 20 registros y acción show para consultar un evento. Formatea datos JSON de manera legible y admite valores históricos de texto. |
| resources/views/bitacoras | index.blade.php | Añade campos de fecha, columna IP y enlace Ver detalle. Conserva filtros y navegación de páginas. Distingue No hay eventos registrados de Sin coincidencias. |
| resources/views/bitacoras | show.blade.php | Nueva vista con fecha, usuario, acción, tabla, ID afectado, IP, valores anteriores y nuevos. Los datos se muestran escapados; no permite editar o borrar eventos. |
| app/Models | Bitacora.php | Añade campo ip y captura la IP de nuevas operaciones web, incluidos los eventos de acceso existentes. Las operaciones de consola dejan IP vacía y los eventos históricos muestran No registrada. |
| app/Observers | RegistrarCambioCritico.php | Nuevo observador que registra CREAR, ACTUALIZAR y ELIMINAR de modelos operativos cuando hay usuario autenticado. Guarda los campos modificados y sus valores anteriores y nuevos. Excluye contraseña, remember_token, token CSRF y marcas de tiempo de esas instantáneas. Los cambios de contraseña registran CAMBIO_CONTRASENA sin guardar su valor. |
| app/Providers | AppServiceProvider.php | Conecta el observador a usuarios, roles, categorías, productos, proveedores, promociones, pedidos, detalles, ventas, movimientos y stocks. No observa Bitacora, evitando registro recursivo. |
| routes | web.php | Añade GET /admin/bitacora/{id}, con auth, consultar_bitacora y parámetro numérico. Conserva la ruta de listado. No incorpora rutas de modificación o eliminación de bitácora. |
| database/migrations | 2026_10_06_000003_completar_bitacora.php | Añade IP nullable de hasta 45 caracteres, apta para IPv4 e IPv6, e índices de fecha, usuario y acción. Aplicada correctamente a MySQL; conserva registros anteriores. |
| tests/Feature | InventarioPedidosTest.php | Añade comprobaciones de filtros, detalle, mensajes vacíos, permisos, métodos no permitidos, auditoría de cambios, IP, ausencia de contraseñas y reversión de registros con transacciones. |

Los eventos existentes de inicio de sesión, cierre, recuperación de contraseña, agotamiento y reposición se mantienen. El observador agrega seguimiento de cambios en los modelos, además de esos eventos específicos. La consulta no modifica los eventos.

## Uso

1. Iniciar sesión con una cuenta que tenga consultar_bitacora.
2. Abrir Bitácora en el menú.
3. Filtrar por fecha desde/hasta, nombre de usuario exacto o acción.
4. Abrir Ver detalle para revisar el evento y los valores registrados.

Los eventos anteriores a la migración no tienen IP; no se inventan direcciones ni se reconstruye información histórica. Se registran cambios realizados a través de modelos Eloquent observados con usuario autenticado; SQL manual, actualizaciones masivas que omiten eventos Eloquent y sincronización directa de tablas pivote no generan automáticamente una instantánea por este observador.

## Conexiones verificadas

| Comprobación | Resultado |
| --- | --- |
| Laravel a MySQL | OK: 127.0.0.1:3308, base cafeteria_pro, consulta SELECT 1 correcta. |
| phpMyAdmin | HTTP 200 en http://localhost/phpmyadmin/. |
| Servidor Laravel | Al comenzar no escuchaba en 8000. Se inició artisan serve en segundo plano; /login respondió HTTP 200. |
| Relaciones Eloquent | Lecturas correctas de productos y stock, pedidos y detalles, ventas, movimientos, promociones y bitácora con sus relaciones. |
| Rutas a controladores | Los métodos referenciados por rutas existentes fueron comprobados. |
| Migraciones | Cero migraciones pendientes. |
| Bitácora con MySQL real | Se generó un evento de actualización y se renderizaron consulta con filtros y detalle. La transacción de prueba fue revertida, conservando los datos existentes. |

El servidor se inició con C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000. Sus registros de inicio están en storage/logs/codex-serve.stdout.log y codex-serve.stderr.log. Está ejecutándose en segundo plano; no hace falta iniciar otro servidor en el mismo puerto mientras siga activo. MySQL se administra por separado desde XAMPP.

## Pruebas

13 pruebas PHPUnit y 175 aserciones aprobadas, usando SQLite temporal en memoria. Incluyen las pruebas previas de inventario, pedidos, pagos, reportes y promociones, además del caso 25.

Se verificó la sintaxis de los archivos PHP modificados. Las vistas se renderizaron mediante pruebas; no se realizó una revisión visual interactiva del navegador. La comprobación de /login confirmó disponibilidad HTTP, sin introducir credenciales ni efectuar un inicio de sesión de prueba. PHP aún muestra la advertencia preexistente de configuración de zip; no impidió las comprobaciones.
