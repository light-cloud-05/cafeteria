# Consulta y aplicación de promociones por mesero y cajero

La promoción Día de San Valentín estaba activa con fechas 6 al 7 de octubre de 2026 y horario de 14:00 a 18:00. La comprobación se realizó a las 23:15 de Bolivia: estaba fuera de horario. Activa significa habilitada por el administrador; Activa y vigente significa que puede aplicarse en la fecha y hora actuales. No se cambiaron sus fechas, horario ni descuento.

## Responsabilidades

- Administrador: crea, edita, activa o desactiva promociones.
- Mesero: consulta las promociones y selecciona una vigente en Nuevo pedido o al modificar un pedido sin pagar.
- Cajero: consulta las promociones y las aplica mediante Corregir pedido / aplicar promoción antes del cobro. El importe se recalcula al guardar.

Para habilitar una promoción durante toda la jornada, el administrador puede editarla y dejar vacías tanto la hora inicial como la final. No basta con marcar Activa si todavía no inicia, venció o está fuera de su horario.

## Cambios

| Archivo | Cambio |
| --- | --- |
| app/Models/Promocion.php | Estado explícito: Inactiva, Configuración incompleta, Programada, Vencida, Fuera de horario o Activa y vigente. La validación de aplicación usa el mismo estado. Horario evaluado en Bolivia, incluyendo el minuto final del intervalo. |
| app/Http/Controllers/PromocionesController.php | Consulta para usuarios con registrar_pedidos, modificar_pedidos o cobrar_ventas, además del administrador. Guardar y activar informa el estado actual. |
| routes/web.php | GET /admin/promociones/disponibles, autenticado; el controlador comprueba los permisos de consulta. |
| resources/views/layouts/app.blade.php | Enlace Consultar promociones para mesero y cajero. |
| resources/views/promociones/index.blade.php | Fecha, horario boliviano y motivo del estado. Crear, editar, activar y desactivar visibles únicamente con gestion_promociones; consulta sin acciones administrativas para otros roles. |
| app/Http/Controllers/PedidoController.php | Incluye todas las promociones para explicar por qué no están disponibles. |
| resources/views/pedidos/form.blade.php | Promociones no vigentes visibles pero deshabilitadas, con descripción de alcance, valor y estado. Enlace a consulta. El total estimado no aplica promociones deshabilitadas. |
| resources/views/ventas/form.blade.php | Botón Corregir pedido / aplicar promoción. |
| tests/Feature/InventarioPedidosTest.php | Comprueba consulta y aplicación con permisos de mesero y cajero, bloqueo de gestión administrativa y rechazo fuera de horario. |

No requiere migración ni cambios de asignación de roles. El servidor mantiene la validación al guardar: no permite aplicar una promoción no vigente aunque se manipule el formulario.

Validación: 18 pruebas y 249 aserciones aprobadas en una base temporal antes del ajuste final del mensaje de confirmación y del cálculo visual. Vistas y cálculo modificados fueron revisados; no se modificaron promociones reales.
