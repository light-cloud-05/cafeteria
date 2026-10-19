# Actualización de tuplas (población de datos)

La carga inicial del sistema comprende tres roles (Administrador, Cajero y Mesero),
20 permisos, sus 28 asignaciones predeterminadas a los roles y tres métodos de
pago (Efectivo, QR y Mixto). Estos datos se definen en los seeders del proyecto.
En Laravel, la carga se realiza mediante `php artisan db:seed`.

La ejecución repetida del seeder principal conserva los atributos de los registros
existentes y las asignaciones de permisos de los roles existentes. Solo asigna
los permisos predeterminados a los roles creados durante esa ejecución.

Los usuarios, categorías, proveedores, productos, existencias y promociones se
registran con información validada de la cafetería. Los pedidos, detalles,
ventas, movimientos y registros de auditoría se generan durante la operación.
No se incluyen cuentas con contraseñas de ejemplo ni transacciones ficticias en
la carga inicial. La primera cuenta administrativa requiere un alta controlada;
el seeder principal no la crea automáticamente.

El archivo `poblacion_informe.sql` contiene la representación SQL de los datos
iniciales para una instalación nueva con las tablas vacías. Es una alternativa
documental a la carga mediante seeders y no debe ejecutarse adicionalmente a
ellos. No es un script repetible: sus INSERT están destinados a una sola carga
inicial. Se eliminan del documento original los TRUNCATE y la desactivación de
claves foráneas, así como las inserciones de datos ficticios.

# Consultas (30 SQL)

El archivo `consultas_informe.sql` presenta diez consultas simples, diez consultas
con JOIN y diez con subconsultas. Utiliza los nombres reales de tablas y columnas
del esquema actual. Las consultas de ventas distinguen las ventas completadas
de los pedidos aún no cobrados y de los cancelados.

El estado de un pedido recién creado es Registrado; al cobrarlo pasa a Pagado y
posteriormente puede pasar a Completado. La cancelación se aplica a pedidos
registrados y sin cobrar. El estado de una venta registrada es Completado.

Las fechas operativas que utilizan el cast FechaLocal se almacenan en UTC y se
presentan en la zona America/La_Paz. Las consultas por día y mes calculan los
límites correspondientes a Bolivia. Las fechas y horas de vigencia de las
promociones se evalúan en horario local. El parámetro de cierre de la consulta
12 es un criterio de ejemplo que debe reemplazarse por el identificador requerido.

La venta conserva su método de pago en el campo metodo_pago. No existe una
clave foránea ventas.idPago ni una columna ventas.idCierre. Por ello, las
consultas no establecen relaciones inexistentes con métodos de pago o cierres.
El campo promociones.producto_ids es una lista JSON; sus elementos no están
protegidos individualmente por claves foráneas.

# Procedimientos almacenados

Las migraciones revisadas no definen procedimientos almacenados. La creación y
modificación de pedidos, el cobro de ventas, las reservas de stock, los movimientos
de inventario y los cálculos de descuentos se realizan mediante controladores y
servicios de Laravel, con las validaciones y transacciones correspondientes.

Se retira el procedimiento sp_RealizarCierreCaja del documento original porque
consulta y actualiza ventas.idCierre, una columna que no existe en la estructura
actual. La existencia de la tabla cierre_cajas no implica que las ventas tengan
una asociación directa con cada cierre. Implementar ese procedimiento requeriría
definir primero el flujo de cierre y su relación con las ventas.

Si se requiere presentar un procedimiento como propuesta académica, puede
utilizarse el siguiente ejemplo de consulta. No forma parte de la implementación
actual ni modifica datos. Debe validarse antes de instalarlo.

```sql
DELIMITER //
CREATE PROCEDURE sp_ConsultarVentasDiarias(IN p_fecha_bolivia DATE)
BEGIN
    DECLARE v_inicio DATETIME;
    DECLARE v_fin DATETIME;
    SET v_inicio = DATE_ADD(TIMESTAMP(p_fecha_bolivia), INTERVAL 4 HOUR);
    SET v_fin = DATE_ADD(v_inicio, INTERVAL 1 DAY);

    SELECT COUNT(*) AS cantidadVentas,
           COALESCE(SUM(total), 0.00) AS totalVendido,
           COALESCE(SUM(monto_efectivo), 0.00) AS totalEfectivo,
           COALESCE(SUM(monto_qr), 0.00) AS totalQR
    FROM ventas
    WHERE estado = 'Completado'
      AND fecha_hora >= v_inicio AND fecha_hora < v_fin;
END //
DELIMITER ;
```

# Triggers (disparadores)

Las migraciones revisadas no crean triggers. La aplicación gestiona las
existencias en stocks, los subtotales, los descuentos, el total de pedidos y la
auditoría mediante código Laravel. Los avisos de existencias mínimas se obtienen
consultando los valores de stock_actual y stock_minimo.

Se retiran los triggers propuestos en el texto anterior. Descontar stock al
insertar un detalle o actualizarlo nuevamente al insertar un movimiento
duplicaría los efectos que ya gestiona la aplicación. Recalcular el total de un
pedido únicamente al insertar detalles tampoco contempla las modificaciones,
cancelaciones y descuentos. El trigger de auditoría utilizaba un idUsuario que
no existe en productos y atribuía cambios a un usuario predeterminado, sin
identificar al responsable real.

La reserva de existencias y su restitución al modificar o cancelar pedidos se
gestionan desde el servicio de reservas. Los movimientos manuales utilizan el
servicio de inventario. Estos procesos deben conservar un único responsable de
actualizar el stock para mantener su coherencia.
