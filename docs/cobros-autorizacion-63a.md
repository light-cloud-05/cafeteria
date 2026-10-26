# Etapa 6.3A — Autorización concurrente del cobro

## Defecto y reproducción

La ruta POST /admin/ventas/cobrar/{id} comprueba auth y permiso:cobrar_ventas mediante
VerificarPermiso. Antes de este ajuste, VentaController::store adquiría el coordinador y
registraba el cobro sin releer la autorización al terminar la espera de GET_LOCK.

Se reprodujo con roles y permisos reales en SQLite :memory:: el actor tenía cobrar_ventas,
se revocó mediante RolController::update en la frontera de espera del coordinador y el cobro
respondió con redirección (302), en lugar de 403. La prueba falló antes de la corrección.
No se probó contra MariaDB ni se modificaron permisos de bases persistentes.

## Cambio mínimo

AutorizacionCaja añade COBRAR (nombre existente cobrar_ventas), cobro() y revalidarCobro(User).
Reutiliza comprobar(), User::tienePermiso(), Rol y su relación rol_permisos. No crea un sistema
de permisos paralelo ni exige consultar_caja o confirmar_cierre_caja para cobrar.

VentaController::store ahora:

1. Autoriza y captura el actor releído antes de validar la entrada y de esperar el coordinador.
2. Adquiere el mismo GET_LOCK existente, sin modificar su nombre, timeout, liberación o conexión.
3. Inicia la misma transacción attempts=1 y revalida el contexto, bloqueando rol → usuario → permisos.
4. Solo entonces asigna la fecha efectiva de cobro, comprueba día abierto y bloquea el pedido.
5. Conserva, sin cambios, versión del pedido, fórmulas monetarias, reserva, creación de venta y
   actualización del pedido a Pagado. usuario_id de la venta procede del actor revalidado.
6. El commit/rollback y la liberación posterior siguen a cargo de los componentes existentes.

Si cambia la identidad o el rol, se rechaza: no se cambia a otro rol dentro de la misma operación.
Si usuario/rol están inactivos o el usuario fue eliminado, tampoco se cobra. El permiso se consulta
según User::tienePermiso y además con una lectura actual FOR UPDATE del pivote/permisos, evitando
aceptar un permiso de una snapshot anterior de InnoDB.

## Orden de bloqueo

Orden del cobro: GET_LOCK → transacción → rol → usuario → permisos/pivote → pedido → reserva/venta.
RolController::update bloquea el rol antes de sincronizar el pivote. Ambas operaciones respetan
rol antes de permisos; no se toma usuario antes del rol ni se bloquea un rol nuevo si cambió la
asignación del actor. Esto evita introducir la inversión con RolController.

No se promete ausencia de todos los interbloqueos posibles de InnoDB. La prueba SQLite verifica
el orden lógico de consultas, pero no implementa FOR UPDATE. Debe repetirse la regresión real
y añadir la revocación mientras se observa User lock, antes de certificar esa espera concreta.
También conviene ensayar cobro frente a edición/inventario con el mismo actor: los nuevos locks
de autorización y los bloqueos/FK de otros módulos pueden interactuar. No se alteran esos módulos
ni se introducen reintentos financieros para ocultar una colisión.

## Fixtures y pruebas

CobrosCoordinadosTest usa ahora User, Rol, Permiso y pivote reales en sus casos, con permisos
registrar_pedidos/cobrar_ventas explícitos. La prueba de revocación invoca el controlador de roles
con un supervisor sintético distinto mientras el cobro ya pasó su comprobación inicial.
Compara pedidos, detalles, ventas, stocks, movimientos, bitácoras, cierres, versiones e instantáneas.
No hay variaciones financieras si falla la revalidación.

Casos añadidos:

- Revocación real en pivote mediante RolController durante la espera controlada: 403 y sin venta.
- Cobrar solo con cobrar_ventas, sin permisos de caja; caja sigue denegada.
- Invocación directa del controlador sin permiso: no adquiere mutex ni escribe.
- Desactivación del usuario o rol y eliminación lógica durante la espera: rechazo y rollback.
- Cambio a un rol diferente que también puede cobrar: rechazo sin buscar/bloquear el nuevo rol.
- Cambio de actor: no sustituye responsable ni genera venta.
- Orden transaccional rol → usuario → permisos antes de pedido.

Los casos anteriores de efectivo, QR, mixto, cambio, promociones, doble cobro, rollback y medianoche
se conservan. InventarioPedidosTest completa únicamente su esquema/usuarios de fixture en memoria
con roles/permisos reales; no se cambian ni debilitan sus verificaciones de negocio.

Runtime::prepararAutorizacionActor del arnés MariaDB agrega idempotentemente cobrar_ventas al
actor sintético id=1/rol=1 de la base marcada, sin retirar asignaciones. Es una adaptación del
fixture para poder llamar a store directamente; no se ejecutó contra MariaDB en esta etapa.
No se necesitan migraciones, permisos nuevos en el catálogo, ni cambios de esquema de aplicación.

Resultado: 228 pruebas, 227 aprobadas, una omitida por concurrencia MariaDB real, 3156 aserciones.
Solo SQLite :memory: y simulaciones. La revocación controlada prueba la frontera autorización/espera,
no una espera física MariaDB. No se repitieron los 51 PASS anteriores con la nueva huella.

## Diagnóstico de precisión monetaria (sin modificar cálculos)

| Archivo / método | Operaciones actuales | Riesgo y límite |
| --- | --- | --- |
| PedidoService::calcular | Precio DECIMAL → float → round(precio*100); multiplicación/suma en enteros; salida /100 | Los enteros centrales ayudan, pero la entrada y salida no son decimales exactos. No se demostró un error de centavo en los casos vigentes. |
| Promocion::descuentoCentavos | Porcentaje/valor/tope a float; multiplicación/división y round | Puntos de medio centavo y aplicación de tope requieren una política explícita de redondeo y casos de frontera. |
| VentaController::store | Collection::sum de subtotal, descuento/QR/recibido a float y round(*100); operaciones posteriores en enteros; persistencia /100 | El paso previo a centavos y la salida usan coma flotante. No cambiar el orden de subtotal/descuento ni volver a descontar promociones al corregirlo en una etapa futura. |
| ReportesController::diarias | Collection::sum sobre importes decimales | PHP puede convertir cadenas DECIMAL a float; acumulación no exacta, especialmente al crecer el rango. |
| ReportesController::pagos | Conversión explícita a float de efectivo y QR; sumas de método y total | No usar como oráculo exacto frente al consolidado. number_format oculta ruido binario pero no hace exacta la suma. |
| Venta y Pedido | Sin casts monetarios explícitos en esos modelos | El tipo leído depende del driver: DECIMAL suele ser cadena en MariaDB, pero SQLite devuelve números. Evitar depender de esa representación. |
| ReservaPedidoService / InventarioService | Stock convertido a float, cantidades validadas como enteros acotados | No es el mismo riesgo que fracciones monetarias: los enteros del rango permitido caben exactamente en la representación habitual. Se conserva todo el código. |

La base DECIMAL conserva escala al persistir, pero no puede recuperar precisión ya perdida antes
de la escritura. Formatear con dos decimales no certifica exactitud. Los límites actuales, enteros
intermedios y round reducen el riesgo en casos habituales; no se afirma que todas las ventas estén
mal calculadas ni que ya se haya probado una discrepancia monetaria en MariaDB.

En pagos mixtos, una venta cuenta en ambos métodos. La suma de cantidades por método no equivale
a ventas únicas; eso no duplica ingresos si se suman solo sus partes efectivo/QR. monto_recibido
incluye el efectivo entregado más QR; no debe usarse como ingreso ni efectivo esperado.

Propuesta posterior, no implementada: conversión decimal exacta de entrada/salida, acumulación
en centavos/BigDecimal, regla de redondeo explícita para porcentaje/topes y pruebas de 0.01,
0.10/0.20, mitades de centavo, límites de importe, múltiples líneas y rangos largos. Primero definir
compatibilidad con resultados históricos y reglas de promociones. No basta con añadir casts al modelo.

## Estado y pendientes

Los cálculos monetarios y clases de pedido/promoción/reportes no se modificaron. No se alteraron
rutas, middleware, GET_LOCK, esquema, P1–P6 o el gate false. Sin bases persistentes, AWS o commits.
La prueba real de revocación durante GET_LOCK, la regresión de 51 casos con el actor actualizado
y las interacciones de bloqueos entre módulos requieren un ensayo aislado posterior autorizado.
