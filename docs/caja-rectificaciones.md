# Etapa 3.2: rectificación versionada y auditoría financiera

No requiere cambios de esquema. Se utilizan las versiones y detalles existentes y los campos JSON de
`bitacoras`. No se añadieron rutas ni interfaces. El gate real de confirmación y rectificación sigue cerrado.

## Operación de dominio

`RectificacionCierreDiarioService::rectificar(cierreId, versionEsperada, efectivoContado, motivo, observacion)`
obtiene el actor del contexto autenticado. Exige `consultar_caja` y `rectificar_cierre_caja` mediante
`AutorizacionCaja` antes de consultar importes o esperar GET_LOCK. No acepta actor, fecha, totales,
diferencias ni ventas suministrados por el cliente. Observación nula elimina la observación de la nueva
versión; las anteriores permanecen intactas. Se rechazan operaciones sin cambios y motivos vacíos.

El mutex es el mismo de cobros/cierres. Dentro de él se inicia una única transacción sin reintentos,
se revalida autorización y se respeta el orden rol → usuario → cierre → versión → detalles.
La versión esperada debe coincidir con la vigente. Se verifica la coherencia de las instantáneas históricas,
incluida su fecha UTC convertida a America/La_Paz, sin recalcular desde las ventas actuales.
Se agrega una versión con idénticos importes del sistema y copias de sus detalles. Solo efectivo contado,
observación y metadatos de rectificación cambian. Se comprueba la diferencia generada con centavos exactos.
La auditoría confirmada es obligatoria dentro de la misma transacción: si falla, la rectificación revierte.
El coordinador existente libera GET_LOCK después de completar commit/rollback.

## Eventos y fallos

- `RECTIFICACION_CAJA`: versión confirmada, valores anteriores/nuevos, actor, fecha, motivo e ID de operación.
- `RECTIFICACION_CAJA_RECHAZADA`: autorización, validación o versión obsoleta.
- `RECTIFICACION_CAJA_FALLIDA`: fallo con resultado de persistencia conocido.
- `RECTIFICACION_CAJA_INCIERTO`: respuesta de commit o coordinación que no permite afirmar fracaso.

Los incidentes se intentan una sola vez fuera de la transacción financiera. Se distingue
`NO_INICIADA`, `ROLLBACK_CONFIRMADO`, `COMMIT_CONFIRMADO` e `INCIERTA`. Un nivel transaccional cero
por sí solo no demuestra rollback: se exige callback de rollback, transacción activa al fallar,
misma conexión y verificación PDO posterior. Una excepción posterior al commit puede coexistir con el
evento confirmado; son eventos diferentes con el mismo ID de operación. No se reintenta la escritura.

Se registra únicamente una lista explícita de datos financieros y códigos de error. No se copian mensajes
de excepciones, SQL, trazas ni observaciones libres a los eventos. Se validan motivo/observación y se
rechazan patrones comunes de secretos; no es un detector universal de información personal.
Sin actor válido o sin conexión utilizable se usa un log estructurado, sin inventar usuarios ni reconectar.
El respaldo no garantiza durabilidad si también falla el almacenamiento de logs; requiere supervisión operativa.
La clave UUID correlaciona eventos por intento; no constituye una restricción única nueva en la base.
Una respuesta incierta exige reconciliar versión y auditoría antes de volver a intentar.

## Históricos y límites

Los modelos de caja y bitácora rechazan modificaciones y eliminaciones Eloquent individuales, silenciosas
y masivas. La creación sigue permitida para los servicios. SQL directo, `DB::table()` y `toBase()` requieren
disciplina y privilegios de base: estas protecciones de aplicación no sustituyen permisos SQL o triggers.
No se añadieron triggers ni cambios de privilegios sin autorización. El controlador antiguo sigue sin rutas.

## Verificación y siguiente paso

Las pruebas ordinarias verifican conexión SQLite `:memory:` y usan usuarios, roles y permisos reales.
Incluyen rectificación, instantáneas, autorización antes y después de esperar, versión obsoleta,
fallos y rollback, fallo posterior al commit, pérdida de sesión, filtrado y protección Eloquent.
Las intercalaciones de SQLite no certifican exclusión entre conexiones MariaDB.

El arnés MariaDB conserva sus nueve escenarios previos (27 ejecuciones aprobadas por el operador antes
de esta etapa) y agrega `rectificaciones-duplicadas` y `rectificacion-y-cobro`. No se ejecutó sobre MariaDB
durante esta etapa. Véase `tests/Integration/CajaMariaDb/README.md` para el entorno desechable y comandos.
Antes de habilitar: nueva regresión real con la huella actual, revisar logs y ensayar pérdidas de conexión
en el entorno aislado. No publicar rutas ni cambiar el gate sin una autorización posterior.
