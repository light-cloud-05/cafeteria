# Actualizaciones de operación

1. Inicio de sesión: botón para mostrar u ocultar la contraseña mientras se escribe.
2. Promociones: precios base visibles, selección de varios productos o todos los productos del pedido. Los descuentos se calculan en el servidor y en la vista previa.
3. Usuarios: eliminación lógica por quienes tienen el permiso de gestión de usuarios; no permite eliminar la propia cuenta y conserva la historia.
4. Stock: se reserva al confirmar el pedido; al editar se recalcula la reserva, al cancelar se devuelve y al pagar se confirma sin otro descuento. Las operaciones son transaccionales.
5. Pagos: efectivo, QR y Mixto. En Mixto se indica la parte por QR y el efectivo recibido; el reporte separa los importes aplicados a cada método.
6. Cantidades: inventario, mínimos y pedidos rechazan decimales. No se redondean datos históricos automáticamente.
7. Búsqueda: campos de búsqueda por nombre en tablas de productos y selectores, incluidos los productos agregados dinámicamente a un pedido.
8. Sesiones: un nuevo inicio de sesión invalida la sesión anterior en su siguiente petición. Una cuenta eliminada o desactivada pierde el acceso.
9. Actualizar: botón en todas las pantallas internas. Los borradores de pedido conservan el comportamiento existente.
10. Número diario: comienza en 1 cada día de Bolivia, mediante un contador con bloqueo. Los identificadores internos se conservan para mantener los enlaces y la historia.
11. Recuperación: el enlace y token llegan exclusivamente a `Proyectocafeteriakarma@gmail.com`. El administrador verifica la identidad antes de autorizar. Puede configurarse con `RECOVERY_ADMIN_EMAIL`.

## Activación

Con MySQL iniciado y accesible según `.env`, ejecutar:

```powershell
php artisan migrate --force
php artisan view:clear
```

La migración agrega columnas, conserva los pedidos existentes y les asigna números diarios. Los pedidos pendientes anteriores a la actualización no tenían reserva; su stock se verifica y descuenta al cobrarlos por compatibilidad. Los pedidos nuevos reservan al registrarse.

El envío requiere que la configuración SMTP existente funcione. Las pruebas verifican el destinatario y el contenido de la notificación, sin enviar correos reales.

## Verificación

```powershell
php vendor/phpunit/phpunit/phpunit --filter InventarioPedidosTest
```

Se verifican reservas, cancelación, edición con reversión ante errores, cantidades enteras, cobros duplicados, pago combinado, reportes, promociones múltiples, cambio de día, sesiones y recuperación al administrador.
