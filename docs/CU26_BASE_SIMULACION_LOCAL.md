# CU26 — Infraestructura de simulación local (Prompt 1)

La interfaz de la segunda fase y el nuevo contrato de escenarios controlados se
documentan en [CU26_INTERFAZ_SIMULADOR_LOCAL.md](CU26_INTERFAZ_SIMULADOR_LOCAL.md).
La descripción siguiente conserva el alcance y resultados de la primera fase.
El cierre del alcance y las verificaciones pendientes se documentan en
[CU26_FINAL_VALIDACION.md](CU26_FINAL_VALIDACION.md).

Esta etapa prepara persistencia y dominio. No implementa pantallas, endpoints del
simulador, autenticación 3DS ni comunicación con WizPay. APROBADO significa un
resultado ficticio local; nunca una confirmación bancaria.

## Activación y destino

La simulación está deshabilitada por defecto en `config/pagos.php`.
`PAGOS_SIMULACION_ACTIVA=true` solo habilita operaciones si también se acredita el
aislamiento de `AislamientoCajaDemo`: entorno demostracion, configuración de demo,
base exclusiva cafeteria_defensa_demo, cuenta karma_defensa_demo, conexión local
TCP no persistente, servidor/puerto esperados y grants limitados. Se conserva la
vigilancia del proveedor existente. No se admite una base arbitraria mediante un
nombre configurable ni un interruptor de request/sesión.

Consultar `docs/DEMOSTRACION_CAJA_LOCAL.md` para las condiciones de la instancia
aislada. No se han modificado archivos de entorno, instalado el esquema en MariaDB
ni preparado datos reales. Las nuevas migraciones requieren una cuenta de
instalación independiente; la cuenta de ejecución de la demo sigue siendo DML.
No ejecutar migraciones contra la configuración normal sin verificar el destino.

La única excepción adicional es PHPUnit en CLI, entorno testing y SQLite
exclusivamente :memory: sin URL ni bases adjuntas persistentes.

## Contrato del dominio

`IntentosPagoService::crear(pedidoId, medio, version, clave)` admite QR_SIMULADO o
TARJETA_SIMULADA y una clave UUID que el cliente conserva por solicitud. No recibe
monto ni estado definitivo. Una repetición con la misma clave, pedido, responsable,
medio y versión devuelve el intento original, incluso terminal. Reutilizar la clave
con otra identidad de solicitud se rechaza. Un reintento posterior usa otra clave.

`procesar(intentoId)` no recibe una aprobación del navegador. Usa exclusivamente
`PAGOS_SIMULACION_RESULTADO`, configuración del backend con APROBADO, RECHAZADO o
PENDIENTE (valor por defecto). Es un escenario local determinista, no una respuesta
del proveedor. El Prompt 2 debe conservar este límite de confianza al añadir UI.
`cancelar(intentoId)` termina un pendiente sin venta. Ambas operaciones exigen
sesión, permiso cobrar_ventas y el mismo responsable del intento.

La vigencia es de 300 segundos. Al procesar un pendiente vencido se persiste
EXPIRADO. Crear otro intento también expira el anterior si corresponde. No hay
tarea programada; los pedidos siguen bloqueados mientras el estado persistido sea
PENDIENTE. La resolución/cancelación repetida de un terminal devuelve su estado sin
efectos adicionales. El modelo rechaza modificaciones, incrementos y borrados de
historia mediante sus operaciones de instancia, incluidas las variantes quietly.
SQL directo y actualizaciones masivas no son interfaces del dominio.

## Atomicidad, bloqueo e inventario

Se conserva el coordinador de cobros/cierres y su política de no reconexión ni
reintento automático cuando un resultado es incierto. La secuencia de escritura es
coordinación -> transacción única -> rol/usuario/permisos -> pedido -> intento ->
productos. El pedido se bloquea también en edición y cancelación antes de comprobar
que no existe un intento pendiente.

`FinalizarCobroService` centraliza el registro presencial y simulado. Su operación
simulada es interna al servicio de intentos y requiere la transacción coordinada;
no debe exponerse como acción HTTP independiente. Relee la autorización y los
registros, verifica versión, importes, estado y día abierto, confirma la reserva,
crea la venta y marca Pagado. La asociación y resolución del intento pertenecen a
la misma transacción. La bitácora se escribe dentro de ella con campos permitidos.
No se guardan payloads, PAN, CVV, vencimientos de tarjeta ni credenciales.

El cálculo usa centavos y verifica precio unitario por cantidad, subtotales,
descuento y total persistido. No toma precios del navegador ni cambia el precio
histórico por el precio actual del catálogo.

La reserva existente se descuenta al registrar un pedido, no de nuevo al aprobar.
Rechazar, cancelar o expirar un intento conserva la reserva del pedido. Cancelar el
pedido, después de resolver el intento, devuelve existencias según el flujo actual.

Las restricciones independientes incluyen venta única por pedido (ya existente),
referencia y clave únicas, venta única por intento asociado y columna generada
pedido_activo_id con índice único para un solo PENDIENTE por pedido. Los intentos
terminales conservan su historial y liberan esa clave activa.

## Ventas, caja e históricos

Las migraciones nuevas agregan monto_tarjeta/origen a ventas, tarjeta_registrado a
versiones y monto_tarjeta/origen a sus instantáneas. Los nuevos campos monetarios
valen cero para históricos. OPERACION es el origen heredado/presencial; no certifica
dinero real en los fixtures previos de la base ficticia. Toda venta del simulador
lleva SIMULACION_LOCAL y un método explícitamente simulado.

Efectivo, QR manual y Mixto conservan sus canales y el significado de sus importes.
La igualdad de caja ahora incluye efectivo + QR + tarjeta. Las rectificaciones
copian los componentes y el origen, y los consolidados incluyen tarjeta. Los
eventos antiguos sin tarjeta se interpretan como cero sin reescribir la bitácora.
El coordinador normal continúa devolviendo false para cierres definitivos.

La simulación solo puede escribir en la base aislada. No copiar ventas/instantáneas
simuladas a una base operativa. Las vistas y reportes se completarán en Prompt 3;
no habilitar el recorrido para usuarios antes de incorporar etiquetas y desglose.

Los rollback nuevos rechazan pérdida de historial de intentos o importes/origen
simulados. No se editaron migraciones históricas ni se eliminó información.

## Verificación y trabajo pendiente

Pruebas: `PagosSimuladosTest` y `PagosEsquemaTest`, además de la regresión existente.
Las pruebas de esquema aplican migraciones únicamente sobre SQLite en memoria y
compilan SQL MySQL/MariaDB con un resolver que falla si intenta abrir un PDO real.
Esto no certifica la ejecución de DDL ni la concurrencia de InnoDB.

Antes del uso manual quedan la instalación verificada del esquema en una instancia
demo vacía, pruebas reales de permisos/grants y concurrencia de dos conexiones
MariaDB (mismo pedido, edición simultánea, cobro presencial contra simulado y
cobro contra cierre). También quedan las pruebas de pérdida de sesión/commit
incierto del nuevo flujo. No se ha accedido a MySQL/AWS para esta implementación.

Prompt 2: añadir rutas con auth, permiso y CSRF; formularios e historial con aviso
permanente de simulación; conservar clave y versión originales al repetir; no
crear endpoints genéricos que acepten estado/monto ni usar fill(request->all()).
Un error de transporte requiere consultar el intento original, no generar otra
clave y cobrar de nuevo automáticamente. El contrato oficial de WizPay Sandbox
será una etapa separada si se consiguen credenciales y documentación verificadas.

## Resultados verificados de esta etapa

- PHP 8.3.36 y PHPUnit 12.5.40.
- Ejecución final: `C:/php83/php.exe vendor/bin/phpunit --do-not-cache-result --display-skipped`.
- 266 pruebas, 3.581 aserciones, cero errores y cero fallos; una omitida:
  `test_concurrencia_real_cobro_cierre_requiere_integracion_y_mariadb`.
- Las 23 pruebas nuevas del CU26 están incluidas en esa ejecución.
- Sintaxis PHP verificada en 38 archivos nuevos/modificados, incluidos cambios
  preexistentes del árbol de trabajo. `git diff --check` correcto.
- PHPUnit se ejecutó fuera del sandbox porque este impedía leer vendor/autoload.php;
  se mantuvo la configuración de SQLite en memoria. No se instalaron dependencias.
- Sin modificaciones de .env, migraciones en bases persistentes, commits, pushes,
  conexiones bancarias ni despliegues.
