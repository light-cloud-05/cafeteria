# Fase 6.2 — Seguridad, recuperación y reconciliación

Estado: implementado y probado solo en memoria/simulaciones. Producción sigue bloqueada.
Referencia del operador: fase 6.1 con 51/51 PASS MariaDB 10.4.32. No se repitió esa integración
ni se alteraron conexiones, datos o servicios persistentes durante esta fase.

## Auditoría del flujo existente

| Componente | Evidencia y límite |
| --- | --- |
| CierreDiarioService::confirmar | Autoriza antes de coordinar, revalida después de esperar, una transacción attempts=1, fecha única, instantáneas y CIERRE_CAJA atómicos. No registra un UUID del intento inicial ni una auditoría de fallo equivalente a la rectificación. |
| RectificacionCierreDiarioService::rectificar | Exige permisos, versión esperada, historial coherente, conserva instantáneas y agrega versión con auditoría atómica. Un callback distingue rollback confirmado; commit sin acuse se trata como incierto. |
| AuditoriaFinancieraCaja | Una confirmación dentro de la transacción; un intento de incidente fuera de ella. UUID común y evento_id distintos para confirmada/incidente. Filtra campos, no copia excepciones y no reconecta para auditar. Sin conexión/actor usa logs. |
| CoordinadorCobrosCaja::exclusivo | GET_LOCK del mismo nombre, conexión única/no persistente, liberación tras commit/rollback, reconexión prohibida durante coordinación, descarte de sesión ambigua. Un error de commit no prueba si fue aplicado. |
| OperacionesCajaController::segura | Rechaza con 503 antes de resolver servicios si el gate está cerrado. CSRF efectivo y permisos por operación. No reintenta; los errores posteriores al servicio deben mantener incertidumbre y mensajes filtrados. |

El gate real permanece false. No se cambian cálculos diarios, transacciones, auditoría, modelo,
GET_LOCK, rutas o paquetes. Los bloqueos por fecha/versión protegen reenvíos manuales, sin convertir
una respuesta perdida en autorización para repetir la operación.

### Defecto reproducido y corregido

Una AuthorizationException lanzada después del commit se propagaba al manejador 403, que incluía
el mensaje interno de la excepción (la prueba usó SQLSTATE/password sintéticos, incluso con debug).
La prueba falló antes del ajuste. También se detectó que un callback podía invalidar Auth después
del commit y provocar un error al renderizar el layout que exige usuario autenticado.

OperacionesCajaController ahora sanea los errores de autorización/contexto posteriores a invocar
el servicio, conserva el código 401/403/404 apropiado y advierte que el resultado no se puede inferir.
No consulta importes después de perder autorización. Si ya no hay actor, retorna un mensaje fijo 401
sin layout, excepción, datos personales ni trazas. La autorización anterior al servicio no se relaja.
Las respuestas siguen private, no-store. No hay reintentos, formularios nuevos ni almacenamiento local.

## Consulta de reconciliación

Nuevo servicio interno `ReconciliacionCajaService::consultar(fecha, versionEsperada?, operacionId?)`.
No se publica una ruta nueva. Se puede utilizar desde un contexto de aplicación ya autenticado:

```php
$lectura = app(\App\Services\Caja\ReconciliacionCajaService::class)
    ->consultar('2026-10-05', 1, $uuidDelIncidente);
```

Para confirmación inicial, omitir versionEsperada; objetivo V1. Para rectificación sobre VN,
pasar N; objetivo VN+1. El UUID es opcional y solo correlaciona la rectificación; nunca designa
al actor ni autoriza escrituras. No establecer Auth desde un identificador libre del navegador.

Requiere **consultar_caja + consultar_bitacora** del actor vigente, sin nuevas asignaciones
automáticas. La consulta financiera es global de la cafetería como el cierre oficial, no exclusiva
del propietario de una venta. Sin ambos permisos se deniega antes de leer cierres o bitácoras.

El servicio:

1. Valida fecha, versión y UUID; exige estar fuera de transacciones Laravel y PDO pendientes.
2. En MariaDB exige conexión primaria sin separación lectura/escritura y aislamiento
   READ-COMMITTED, REPEATABLE-READ o SERIALIZABLE. Rechaza READ-UNCOMMITTED.
3. Busca solo la cabecera oficial de esa fecha, sus versiones y sus instantáneas.
4. Contrasta fechas UTC/Bolivia, unicidad de ventas por versión, total=efectivo+QR, sumas históricas
   y diferencia exacta. No reconstruye desde ventas/precios actuales.
5. Comprueba secuencia de versiones, un único evento confirmado por versión, actor, fecha, importes,
   y UUID/evento_id de rectificación. Los incidentes distintos no se cuentan como confirmaciones duplicadas.
6. Devuelve una lista explícita de importes, IDs de versión/evento/actor, conteos y estados; no incluye
   observaciones, motivos, contraseñas, tokens, SQL, IP o datos completos de usuarios.

| Estado | Interpretación permitida |
| --- | --- |
| SIN_CIERRE_OBSERVADO | No se observa cabecera oficial en esa consulta. NO demuestra rollback ni que no haya operación en curso. |
| VERSION_OBJETIVO_NO_OBSERVADA | Existe cierre, pero no se observó VN+1. NO autoriza reintentar. |
| CONFIRMACION_OBSERVADA | Se observan registros coherentes y evento confirmado único. No prueba recuperación de una conexión ni atribución al intento inicial sin identificador. |
| REQUIERE_REVISION | Auditoría ausente/duplicada/incoherente, instantáneas inválidas, secuencia irregular, fecha no terminada o UUID que no coincide. No reparar automáticamente. |

Siempre devuelve `reintento_automatico_permitido=false`. La atribución es NO_VERIFICADA si no se
proporciona UUID; con UUID se informa COINCIDE/NO_COINCIDE. La versión vigente observada se informa
separadamente de la versión objetivo, porque pueden existir rectificaciones posteriores.

No se adquieren locks, se escriben eventos, se corrigen datos ni se marcan incidentes resueltos.
Las lecturas pueden realizarse en momentos distintos durante una rectificación: son evidencia
observada, no una congelación ni una prueba de ausencia definitiva. Una réplica/proxy no declarado
o sesión todavía en curso requiere revisión operativa adicional. Si la consulta falla, no hay conclusión.

## Procedimiento operativo sin reintentar

1. Detener el envío: no pulsar de nuevo confirmar/rectificar, no automatizar F5 ni reabrir un POST.
   Conservar fecha operativa, versión esperada, hora, actor autenticado y UUID del incidente si existe.
   No guardar tokens o contraseñas como evidencia. El gate sigue bloqueado durante esta fase.
2. Un supervisor con ambos permisos inicia una sesión válida y consulta la conexión primaria del
   entorno autorizado. Si no hay sesión/base fiable, detenerse; no cambiar credenciales o base para forzar.
3. Usar los GET existentes: listado filtrado por fecha, detalle del cierre e historial. Para VN buscar
   VN+1 y revisar si hay versiones posteriores. Una cabecera NULL es histórica, no cierre oficial.
4. Consultar la bitácora autorizada: CIERRE_CAJA para V1 o RECTIFICACION_CAJA para VN+1, registro
   afectado de la versión, actor e importes. Para rectificación, correlacionar operacion_id y evento_id
   con el incidente. Un evento confirmado y un incidente incierto del mismo UUID no son un duplicado.
5. Contrastar instantáneas y versión con ReconciliacionCajaService cuando se disponga de un contexto
   interno autorizado. No pegar resultados JSON completos de bitácora ni logs sin filtrar en la interfaz.
6. Si hay confirmación coherente y correlación suficiente, registrar fuera del flujo automático la
   revisión del supervisor. No crear una nueva versión ni modificar el evento para «completar» el intento.
7. Si faltan registros, comprobar que la sesión anterior terminó y que no sigue una operación en curso
   desde una sesión independiente del entorno desechable. La ausencia por sí sola NO prueba rollback.
   Si hay inconsistencias o UUID distinto, escalar; no deducir que el intento propio fracasó.
8. Solo una decisión humana posterior y autorizada puede iniciar una operación nueva, volviendo a
   consultar la versión vigente. Esta fase no habilita esa acción ni concede permisos para hacerlo.

La confirmación inicial no tiene UUID de intento persistido: fecha única + V1 + actor/importes
permiten constatar el cierre, pero no atribuir inequívocamente cuál de dos solicitudes concurrentes
lo creó. Resolver eso exigiría un contrato de idempotencia nuevo, no implementado en esta fase.

## Fallos controlados: qué se ejecutó

`tests/Support/PdoCommitCajaSimulado` es un PDO SQLite real creado exclusivamente con sqlite::memory:.
Puede lanzar una excepción antes de ejecutar commit o después de que parent::commit ya lo aplicó.
No cierra conexiones de red ni toca el servidor. Así se obtiene evidencia real de persistencia en
memoria con el mismo error sintético visible para la aplicación en dos resultados diferentes.

| Escenario | Evidencia de las pruebas | Alcance |
| --- | --- | --- |
| Excepción en instantánea antes de commit | Se revierten versión y detalles; incidente ROLLBACK_CONFIRMADO; ventas/reservas intactas | SQLite real en memoria |
| Excepción de commit sin aplicarlo | El coordinador de pruebas revierte; auditoría conserva INCIERTA porque no tiene prueba suficiente del commit | SQLite + fallo sintético |
| Commit aplicado con respuesta perdida | Una versión/evento confirmados, incidente INCIERTO y lectura posterior coherente sin reintento | SQLite + fallo sintético |
| Error posterior a commit/liberación | Commit confirmado e incidente con evento_id diferente; sin duplicación financiera | Adaptador de pruebas |
| Commit aplicado/no aplicado, GET_LOCK y desconexión | Callback y commit una sola vez, resultado incierto, PDO descartado | PDO MariaDB simulado, sin red |
| Revocación al terminar la espera | Autorización se revalida y revierte antes de escribir | Intercalación controlada SQLite, NO espera real GET_LOCK |
| Reenvío manual | Fecha duplicada o versión obsoleta rechazada; una confirmación financiera | Memoria, nunca automático |
| Incidente llamado dos veces | Un intento de auditoría por objeto de operación | Memoria; no es restricción única SQL global |
| Auditoría duplicada o manipulada | Reconciliación exige revisión, no elimina ni cambia registros | Fixture negativo en memoria |
| Permisos, histórico NULL y transacción externa | Denegación previa, exclusión o rechazo; sin lectura propia no confirmada | Memoria |
| Autorización o sesión perdidas tras commit | Respuesta segura, sin SQL/token/contraseña ni fallo de layout | Pruebas HTTP con CSRF efectivo |

Las pruebas comparan ventas, pedidos, detalles, stocks, productos y movimientos antes/después,
además de versiones, instantáneas y eventos. Conservan las pruebas previas de protección Eloquent,
CSRF, manipulación, gate, ausencia de rutas genéricas y lectura autorizada.

Comando seguro, solo SQLite en memoria y mocks:

```powershell
$env:DATABASE_URL = ''
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_FOREIGN_KEYS = 'true'
& C:\php83\php.exe vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --do-not-cache-result --display-skipped
```

Resultado: 221 pruebas, 220 aprobadas, una omitida de integración MariaDB real, 3105 aserciones.
No se ejecutaron migraciones/seeders en bases persistentes ni el arnés MariaDB. Sin nuevas rutas,
tablas, permisos, modificaciones de P1–P6, cambios de GET_LOCK ni habilitación de producción.

## Qué NO queda certificado y prueba aislada adicional

No se certifican recuperación ante pérdida real de conexión, duración real de locks tras una
sesión descartada, respuesta perdida a través de red/proxy, revocación durante una espera real
GET_LOCK, ni durabilidad bajo caída del servidor. Los 51 PASS previos cubren sus escenarios, no estos.
El nuevo servicio consulta @@tx_isolation en MariaDB; esa rama requiere verificación aislada real.

Para una fase posterior autorizada, preparar únicamente en cafeteria_caja_integracion y con
karma_caja_it un cliente/proxy local de fallos, dedicado y sin tráfico de otras aplicaciones.
No detener MariaDB ni usar SHUTDOWN/KILL o alterar las bases originales. El proxy debe permitir
interrumpir solo la conexión del cliente de prueba y registrar fases sin capturar credenciales.
No se implementó ni ejecutó aquí ese proxy.

Protocolo propuesto para ese ensayo adicional:

1. Repetir la regresión de 17 escenarios con la nueva huella (README del arnés); confirmar base,
   versión, cuenta y sesiones reales antes de cualquier fixture. El gate permanece false.
2. En dos sesiones de aplicación independientes, separar: fallo antes de enviar COMMIT, después
   de enviarlo sin conocer respuesta, y después de observar persistencia desde una tercera sesión.
   No afirmar que se controló la fase exacta si no hay evidencia del protocolo.
3. Desde una sesión independiente y autorizada, observar versión, evento, instantáneas y
   IS_USED_LOCK del nombre exacto; verificar ausencia de escrituras repetidas y límites de espera.
4. Para revocación real, retener el mutex sin bloquear roles; iniciar otra operación y observar
   User lock en PROCESSLIST. Revocar el permiso del actor sintético con orden rol/pivote, confirmar
   esa transacción y liberar el mutex. La operación que esperaba debe denegarse sin crear versión.
   Preparar esa variación en un fixture nuevo, sin modificar roles reales ni reciclar incidentes.
5. Reconciliar con la sesión primaria sin transacción pendiente, sin invocar confirmar/rectificar
   como parte de la recuperación. Conservar evidencia, resultados agregados y huella del código.

Ese ensayo necesita implementación y autorización separadas antes de declarar recuperación real
certificada. No hay instrucciones de alterar servicios o ejecutar pérdidas reales en esta entrega.

## Riesgos restantes

- La confirmación no registra incidentes estructurados como la rectificación; ausencia de evento
  confirmado no prueba nada sobre un commit cuya respuesta se perdió.
- Fallos simultáneos de BD y almacenamiento de logs impiden garantizar durabilidad de incidentes.
- SQL administrativo/DB::table/toBase eluden los guardas Eloquent; las rutas públicas no los exponen.
- El UUID y el flag de incidente evitan duplicación dentro de una invocación, no aportan una clave
  única/idempotencia distribuida. La reconciliación detecta confirmaciones duplicadas y exige revisión.
- Los avisos conservadores pueden mostrarse en fallos que sí se revirtieron. Evitan prometer fracaso
  cuando no puede conocerse el resultado; el código HTTP no es una prueba de persistencia.
- Los glob de la huella del arnés incluyen el servicio nuevo; 51 PASS de una huella anterior no lo
  certifican. El arnés actual no otorga consultar_bitacora a su actor: una futura prueba específica
  de reconciliación deberá asignarlo explícitamente solo al actor sintético, sin privilegios por nombre.
