# Historial de pedidos siguiente cobro y hora de Bolivia

## Pedidos 4 y 5

La consulta de MySQL confirmó que no existen pedidos con ID 4 o 5, ni eventos de bitácora de esos pedidos. El historial consulta todos los pedidos persistidos según los filtros y no los oculta por haber sido corregidos por caja.

Las verificaciones anteriores en MySQL crearon pedidos dentro de transacciones que luego se revirtieron. MySQL consume los números autoincrementales aunque se revierta la transacción, por lo que estas verificaciones dejaron saltos en la numeración. No se renumeran pedidos ni se inventan registros para rellenar los saltos. La edición de un pedido mantiene su ID y usuario creador; la bitácora identifica al cajero que realiza la modificación.

Las pruebas de esta corrección se realizaron con SQLite temporal para no consumir más números de pedidos en la base real.

## Siguiente pedido

Se añade Siguiente pedido en el detalle mostrado después de cobrar y en el formulario de cobro. Abre el pedido Registrado sin venta más antiguo, excluyendo el actual. Omite pagados y cancelados. Si no quedan pendientes vuelve a Ventas y cobros con un mensaje.

- app/Http/Controllers/VentaController.php: acción siguiente, con selección por fecha e ID.
- routes/web.php: GET /admin/ventas/siguiente/{id}, con auth y cobrar_ventas.
- resources/views/pedidos/show.blade.php: botón para el cajero.
- resources/views/ventas/form.blade.php: acceso directo al siguiente pendiente.

## Hora de Santa Cruz Bolivia

config/app.php usa America/La_Paz, la zona horaria de Bolivia que corresponde a Santa Cruz. Las fechas operativas existentes siguen guardadas en UTC y se convierten a hora boliviana para mostrarse. Las nuevas fechas operativas también se guardan en UTC, evitando mezclar horas locales y UTC en las columnas existentes.

- app/Casts/FechaLocal.php: convierte UTC a hora de Bolivia al leer y convierte hora local a UTC al guardar.
- app/Models/Pedido.php, Venta.php, MovimientoInventario.php, Stock.php y Bitacora.php: aplican la conversión a fechaHora, fecha_hora o fecha_actualizacion según el modelo.
- app/Models/Concerns/FiltraFechaLocal.php: transforma un día o rango de fechas boliviano a límites UTC para consultar.
- app/Http/Controllers/PedidoController.php, ReportesController.php y BitacorasController.php: filtros por día local, incluyendo las operaciones nocturnas que en UTC pertenecen al día siguiente.
- resources/views/bitacoras/index.blade.php y show.blade.php: etiqueta Fecha y hora de Bolivia.

Se limpiaron las cachés de configuración y vistas. No se cambió la hora de Windows ni se reescribieron las fechas de los registros existentes. Los horarios de promociones se evalúan según la hora boliviana configurada.

Ejemplo real verificado: el pedido 6, que se mostraba como 2026-10-07 02:39:22, ahora se muestra como 2026-10-06 22:39:22.

## Validación

- 17 pruebas y 230 aserciones aprobadas en una base temporal.
- Siguiente pendiente, exclusión de pagados y cancelados y lista sin pendientes.
- Almacenamiento UTC, visualización de Bolivia, historial y reportes diarios con cruce de medianoche.
- Conserva las pruebas anteriores de modificación desde caja, cobros, stock, promociones y auditoría.
- Consulta real de solo lectura: MySQL conectado, zona America/La_Paz y hora corregida del pedido 6.
- No requiere migración ni cambios de credenciales o puerto.
