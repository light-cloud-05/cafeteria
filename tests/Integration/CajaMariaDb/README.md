# Integración real de caja en MariaDB 10.4.32 y 10.11.14

Estado: preparada, **no ejecutada en MariaDB por Codex**. No habilita cierres de producción.
Última referencia aportada por el operador: 33/33 ejecuciones PASS de los once escenarios, posteriores
a la fase 4. La ampliación de etapa 6.1 está preparada; esos resultados previos no certifican la nueva
huella ni los casos de consolidados hasta volver a ejecutar en la base desechable.
Esta carpeta no forma parte de las suites ordinarias `Unit` y `Feature` de phpunit.xml.
El ejecutor es un CLI PHP independiente; no necesita pcntl, servidores HTTP ni rutas nuevas.

## Límites de seguridad

- Única base admitida, sin parámetros alternativos: `cafeteria_caja_integracion`.
- Nunca se utilizan credenciales de `.env`, ni `cafeteria_pro` ni `cafeteria_pro_pruebas`.
- Cuenta requerida: `karma_caja_it`, local y con permisos exclusivamente en esa base.
- `SELECT DATABASE()`, `VERSION()`, `CONNECTION_ID()` y `CURRENT_USER()` se comprueban antes de cargar Laravel.
- La cuenta autenticada debe ser exactamente `karma_caja_it@localhost`, `karma_caja_it@127.0.0.1`
  o `karma_caja_it@::1`. Se rechazan cuentas anónimas, administrativas, remotas o con host `%`,
  aunque el usuario configurado sea correcto. No se publica esa identidad ni la contraseña.
- Un hook repite la comprobación real antes de cada sentencia Laravel, incluido cada INSERT y DDL.
- Se bloquean `USE`, `DROP`, `TRUNCATE`, consultas a los nombres protegidos y reconexiones.
- `--prepare` exige cero tablas/vistas y se niega a reutilizar una base poblada. No usa seeders ni migrate:fresh.
- Las ejecuciones posteriores anexan fixtures sintéticos y fechas nuevas; no borran datos para reiniciarse.
- Un bloqueo independiente impide ejecutar dos runners simultáneos sobre la misma base.
- Los workers heredan la contraseña por entorno, nunca por argumentos ni por archivos de tarea.
- No se cambia el booleano de habilitación del coordinador de producción. El adaptador que permite cerrar
  existe únicamente en `Runtime.php`, comprueba la base y su marca, y llama al coordinador real.

## Requisitos

1. PHP `C:\php83\php.exe` (8.3.36 recomendado), dependencias vendor instaladas, pdo_mysql y proc_open habilitados.
2. MariaDB **10.4.32** o **10.11.14** local, por ejemplo `127.0.0.1:3308`, coincidente con la selección esperada.
3. Cuenta dedicada con SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX y REFERENCES solo en la base desechable.
4. Debe poder observar sus propias sesiones en information_schema.PROCESSLIST. No se concede PROCESS global
   automáticamente. Si una política oculta STATE, la suite falla; no reemplaza la evidencia por una espera fija.
5. Proyecto en `feature/cierre-caja`, sin configuración Laravel cacheada. Si existe bootstrap/cache/config.php,
   el arnés se detiene: no elimina cachés ni altera configuración por su cuenta.
6. Permiso para crear archivos temporales y dos procesos PHP hijos. No ejecutarlo desde PHPUnit ordinario.

## Preparación manual, una sola vez

### Selección de versión para cualquier comando con conexión

Configurar en la misma sesión de PowerShell, antes de `--prepare` o de ejecutar casos:

```powershell
$env:KARMA_CAJA_EXPECTED_VERSION = '10.11.14'
# Para conservar la línea base explícita, usar en su lugar:
# $env:KARMA_CAJA_EXPECTED_VERSION = '10.4.32'
```

Solo se aceptan esos dos valores exactos. Un valor vacío, con espacios o desconocido se rechaza
antes de abrir una conexión. Si la variable está **ausente**, se conserva el comportamiento anterior:
se exige **10.4.32**, nunca se detecta automáticamente ni se acepta 10.11.14 por omisión.
La versión real debe identificar MariaDB y coincidir exactamente con el parche seleccionado;
se admiten el prefijo de compatibilidad `5.5.5-` y los sufijos de distribución.
No cambiar la selección durante una ejecución. Los workers heredan el entorno y reciben también
la selección en su tarea; rechazan discrepancias antes de conectar. El hook conserva la versión
esperada inicial para comprobar cada sentencia, además de base e identidad autenticada.

El reporte incluye `version_esperada` y `version_real` del padre (también conserva `servidor`);
cada caso concurrente incluye `workers` con sesión, versión esperada y real de ambos hijos.
Si falla la validación inicial, no se afirma una versión real verificada: el error es sanitizado.
Esta adaptación no se ha ejecutado contra ninguna de las dos versiones de MariaDB.

Un **PASS no habilita cierres**, no certifica la capacidad del servidor (carga, recursos o concurrencia
de producción), ni demuestra revocación de permisos durante una espera real `User lock`.
Los escenarios existentes conservan su alcance; seleccionar 10.11.14 no agrega esas certificaciones.

Verificación sin MariaDB de esta adaptación (PHP 8.3.36):
`php vendor/phpunit/phpunit/phpunit --filter 'CajaIntegracionGuardTest|CajaMariaDbConsolidadosFixturesTest'`:
15 pruebas aprobadas, 164 aserciones. PDO se simula en las pruebas del guard; los fixtures usan SQLite
en memoria. Se comprobó también sintaxis de los PHP del arnés y `run.php --help`, sin conexión.

Los siguientes pasos son para una ejecución posterior por el operador. Codex no los ejecutó.

Primero revisar la rama y el PHP, desde PowerShell:

```powershell
Set-Location C:\xampp\htdocs\cafeteria_pro
git branch --show-current
& C:\php83\php.exe -v
& C:\php83\php.exe -m
```

Con una cuenta administrativa local, crear una base **nueva**, sin IF NOT EXISTS:

```powershell
& C:\xampp\mysql\bin\mysql.exe --host=127.0.0.1 --port=3308 --user=root -p --execute="CREATE DATABASE cafeteria_caja_integracion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if ($LASTEXITCODE -ne 0) { throw 'No se creó una base nueva. Detenerse y revisar; no borrar bases automáticamente.' }
```

Si mysql.exe está en otra ubicación, utilizar su ruta real. `-p` solicita la contraseña sin ponerla en el comando.
Si la base ya existe, el CREATE falla: no ejecutar borrados ni migrate:fresh como solución automática.

Crear manualmente, mediante el administrador local de MariaDB/phpMyAdmin, la cuenta **karma_caja_it**,
host **127.0.0.1**, con contraseña nueva y sin permisos globales. Asignarle exclusivamente
SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX y REFERENCES sobre `cafeteria_caja_integracion`.
No reutilizar root para el arnés; este rechaza otros nombres de usuario.

Configurar solo el entorno del PowerShell actual; no editar .env ni guardar contraseñas en archivos:

```powershell
$env:KARMA_CAJA_ALLOW = 'cafeteria_caja_integracion'
$env:KARMA_CAJA_DB = 'cafeteria_caja_integracion'
$env:KARMA_CAJA_HOST = '127.0.0.1'
$env:KARMA_CAJA_PORT = '3308'
$env:KARMA_CAJA_USER = 'karma_caja_it'
$claveCaja = Read-Host 'Contraseña de karma_caja_it' -AsSecureString
$env:KARMA_CAJA_PASSWORD = [System.Net.NetworkCredential]::new('', $claveCaja).Password

& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --prepare
if ($LASTEXITCODE -ne 0) { throw 'Preparación fallida: detenerse y revisar.' }
```

`--prepare` aplica únicamente los métodos up() históricos en la base vacía, crea dos tablas de control
y fixtures sintéticos mínimos. No registra lotes oficiales en la tabla migrations y no debe mezclarse
con `artisan migrate`. Si falla a mitad, no intenta reparar ni reiniciar la base: requiere revisión manual.
Una preparación ya completada se usa con --case; no repetir --prepare.

Adaptación de fase 3.1: antes de lanzar workers, el padre registra idempotentemente los tres permisos
de caja y los agrega sin retirar asignaciones al actor sintético **id=1, rol=1**. Exige la marca de la base,
el identificador y el nombre exacto del fixture. No usa el nombre del rol para conceder privilegios.
También permite reutilizar fixtures de integración anteriores sin borrar ni recrear tablas. Esta escritura
ocurrirá solo cuando el operador ejecute el arnés; no afecta a roles de las bases de la aplicación.

## Comandos de ejecución

Todos los casos, con tres repeticiones, en la misma sesión de PowerShell:

```powershell
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=all --repeat=3
if ($LASTEXITCODE -ne 0) { throw 'Integración no aprobada. Conservar el reporte y mantener bloqueados los cierres.' }
```

Casos individuales:

```powershell
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=dos-cobros
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=cobro-y-cierre
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=cierres-duplicados
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=cobro-durante-cierre
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=medianoche
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=timeout
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=rollback
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=mismo-pedido
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --case=fecha-cerrada
```

Detenerse ante cualquier código de salida distinto de cero. Para repetir un caso fallido, primero revisar sus
resultados y confirmar que terminaron los procesos hijos; cada nuevo intento usa nuevos datos y fechas.

Ayuda y revisión sintáctica sin conexión a MariaDB:

```powershell
& C:\php83\php.exe tests\Integration\CajaMariaDb\run.php --help
Get-ChildItem tests\Integration\CajaMariaDb\*.php | ForEach-Object { & C:\php83\php.exe -l $_.FullName }
```

Al terminar, retirar la contraseña del entorno:

```powershell
Remove-Item Env:KARMA_CAJA_PASSWORD -ErrorAction SilentlyContinue
Remove-Variable claveCaja -ErrorAction SilentlyContinue
```

## Qué ejecuta y comprueba

Cada caso crea un producto y pedidos mediante PedidoController::store(), con reservas reales del esquema
de prueba. Dos procesos PHP distintos, con conexiones MariaDB distintas, invocan el método real
VentaController::store() o CierreDiarioService::confirmar(). No prueban las rutas, permisos ni sesiones web.
El padre verifica que las tres CONNECTION_ID sean distintas. A se detiene en una barrera dentro de su
transacción, manteniendo el mutex real. B debe aparecer en PROCESSLIST con STATE `User lock` antes de
liberar A. Los resultados no se basan en dormir un tiempo y asumir que hubo concurrencia.

| Caso | Evidencia exigida |
|---|---|
| dos-cobros | Dos pedidos cobrados una vez, espera real y reservas sin doble descuento. |
| cobro-y-cierre | Cobro fechado antes de medianoche que termina antes de adquirir el cierre; su venta está incluida. |
| cierres-duplicados | Un cierre y una versión; el segundo proceso recibe validación por fecha. |
| cobro-durante-cierre | El cobro espera mientras se cierra ayer; al continuar pertenece a hoy y no altera el cierre de ayer. |
| medianoche | La solicitud B empieza con reloj anterior a medianoche, espera a A y toma la fecha nueva tras adquirir el mutex. |
| timeout | B agota GET_LOCK mientras A sigue reteniéndolo; B nunca entra al callback ni crea venta. |
| rollback | A falla después del INSERT de venta; B continúa tras rollback, sin venta residual ni modificación de reservas de A. |
| mismo-pedido | Un único cobro; el segundo intento no duplica venta ni descuenta existencias otra vez. |
| fecha-cerrada | Después de esperar el cierre, un cobro cuya fecha efectiva ya está cerrada se rechaza. |

En cada cierre se compara el conjunto EXACTO de ventas completadas del día UTC/Bolivia con las instantáneas,
y se concilian total, efectivo y QR mediante centavos, sin floats. Se revisan estados de pedidos, conteos,
reservas y movimientos. Al terminar debe quedar libre el bloqueo de operaciones.

Las conexiones son reales; el reloj de aplicación se controla por proceso para hacer reproducible la
medianoche. No se cambia el reloj del sistema ni el de MariaDB. Esto no sustituye una prueba adicional
con relojes físicos sincronizados durante un cambio real de fecha.

## Interpretación del reporte

- Salida 0 y `estado: PASS`: todos los casos seleccionados cumplieron sus invariantes.
- Salida 1 o cualquier `FAIL`: no aprobar; revisar el reporte y las evidencias del caso.
- La consola imprime la ruta `%TEMP%\karma-caja-it-...\report.json`.
- El JSON incluye casos, repeticiones, resultados agregados, versiones y huella SHA-256 del código probado.
- Cada subdirectorio guarda tareas sintéticas, sesiones, barreras y resultados de workers, sin contraseñas.
- No se imprimen mensajes PDO ni trazas con argumentos. Un error inesperado incluye clase, archivo y línea.
- `habilita_cierre_produccion` permanece false incluso con PASS. El arnés no cambia código ni configuración.
- `--case=timeout` y `--case=rollback` deben terminar en PASS: el fallo esperado del worker es la condición probada.

No se borran evidencias, fixtures ni bases automáticamente. Su retirada posterior es una decisión manual
del operador exclusivamente sobre esta base desechable.

## Intervención manual y condiciones antes de habilitar cierres

Requieren intervención: crear base/cuenta, introducir credenciales, ejecutar y revisar reportes; comprobar
que no quedan workers tras una interrupción forzada; ensayar pérdida real de conexión durante adquisición,
operación y commit en un entorno aislado. Un commit de respuesta perdida requiere consultar el resultado,
no reintentar a ciegas. Las pruebas de red/KILL CONNECTION no están automatizadas aquí.

Antes de habilitar: todos los casos PASS repetidos en MariaDB 10.4.32, sin omisiones, huella del código
coincidente con el que se desplegará, revisión de los fallos de conexión y de la liberación de sesiones,
configuración no persistente y sin proxies que cambien de sesión, y todos los procesos usando esta versión
del coordinador (retirar o drenar procesos antiguos). El cambio del gate requiere una fase autorizada aparte.

## Auditoría de escritores de ventas

En app/ y routes/, la única creación persistente encontrada es VentaController::store(), ya coordinada.
La ruta de corregir pedido modifica pedidos sin cobrar; no registra ventas por otro camino.
Fuera del flujo web, VentaSeeder::run() crea ventas sin pasar por el coordinador. DatabaseSeeder no lo
invoca actualmente. La migración 2026_10_11_000001 actualiza desgloses históricos mediante Query Builder.
No ejecutar esos procesos sobre el sistema en operación mientras hay cobros/cierres. SQL administrativo,
importaciones y escrituras futuras también deben participar en el protocolo o ejecutarse sin tráfico.
Esta revisión de código no demuestra que no existan escritores externos a este repositorio.

Referencias: [GET_LOCK](https://mariadb.com/docs/server/reference/sql-functions/secondary-functions/miscellaneous-functions/get_lock),
[proc_open en Windows](https://www.php.net/manual/en/function.proc-open.php).

## Regresión de rectificaciones (etapa 3.2)

Se mantienen los nueve escenarios anteriores y se agregan dos, con el mismo guard de base desechable,
procesos independientes y observación real de espera User lock. Estos nuevos casos están preparados;
no se han ejecutado en MariaDB durante esta etapa.

- `rectificaciones-duplicadas`: A agrega versión 2; B espera y rechaza su versión esperada 1.
  Se comprueban dos versiones exactas, auditoría confirmada y rechazada únicas e historial anterior intacto.
- `rectificacion-y-cobro`: A rectifica el día anterior mientras B espera para cobrar en el siguiente.
  Se comprueban reservas, pedidos, ventas, instantáneas, diferencia y ausencia de cambios al historial.

Con las variables de conexión y consentimiento ya configuradas según las secciones anteriores,
exclusivamente en `cafeteria_caja_integracion` marcada y preparada:

```powershell
& C:\php83\php.exe tests/Integration/CajaMariaDb/run.php --case=rectificaciones-duplicadas --repeat=3
& C:\php83\php.exe tests/Integration/CajaMariaDb/run.php --case=rectificacion-y-cobro --repeat=3
& C:\php83\php.exe tests/Integration/CajaMariaDb/run.php --case=all --repeat=3
```

La suite completa ahora produce 33 ejecuciones (11 casos × 3). Los 27 PASS previos son evidencia de
la versión anterior; deben repetirse con la nueva huella. No ejecutar `--prepare` en una base ya preparada.
Ningún PASS cambia el gate real de producción.

## Etapa 6.1 — Suite ampliada de consolidados (preparada, no ejecutada en MariaDB)

El catálogo está en `Catalogo.php`. Se conservan los once casos, workers, barreras y comprobaciones
de concurrencia. **Sin --suite, --case=all sigue ejecutando esos 11 casos**, no los nuevos fixtures.
La línea base aportada por el operador es 33/33 PASS; no certifica todavía la nueva huella.

| Selección | Casos por repetición | Con --repeat=3 |
| --- | ---: | ---: |
| --suite=concurrencia | 11 anteriores | 33 |
| --suite=consolidados | 6 nuevos | 18 |
| --suite=ampliada | 17 en total | 51 |

Los casos añadidos son:

1. **conso-decimales**: verifica information_schema (DECIMAL(16,2), diferencia DECIMAL(17,2)
   STORED GENERATED e InnoDB). Exige que PDO devuelva cadenas exactas. Dos cierres máximos y un
   centavo QR producen total **199999999999999.99**, efectivo esperado **199999999999999.96**,
   QR **0.03**, diferencia neta **0.00**, faltante **-0.01** y sobrante **0.01**. El anual debe coincidir.
   Rechaza SQLite antes de escribir: los máximos requieren MariaDB real.
2. **conso-versiones**: tres versiones e instantáneas conservadas, solo V3 incluida, diferencia
   positiva de un centavo, un cierre histórico con fecha operativa NULL excluido y cantidad de
   ventas sin duplicación de instantáneas históricas. Comprueba que V1 y su detalle no cambiaron.
3. **conso-completo**: cierre explícito cero para todos los días del año sintético (365 o 366),
   doce meses completos y conciliados, comparación de cada mes con el anual y sus días exigibles.
4. **conso-incompleto**: cero explícito, cierre con faltante de un centavo, días sin cierre,
   venta sin cierre que no se incorpora al total, día abierto y días futuros separados.
5. **conso-futuros**: frontera enero/febrero UTC con día de Bolivia todavía abierto, mes y año
   futuros sin pendientes exigibles y año pasado vacío e incompleto.
6. **conso-consistencia**: 0.10 + 0.20 + 0.30 = 0.60, comparación diario/mensual/doce meses/anual,
   tres ventas incluidas y exclusión del centavo del año anterior.

### Integridad y límites de los fixtures

Antes y después de cada consulta se calculan SHA-256 de todo el contenido ordenado de ventas,
pedidos, detalles, cierres, versiones, instantáneas, bitácoras, stocks, movimientos, productos,
usuarios y rol_permisos. No se imprimen filas ni información de usuarios. Un hook permite únicamente
SELECT durante las consultas e impide GET_LOCK, RELEASE_LOCK o FOR UPDATE; el anual debe realizar
una sola lectura financiera. No confundirlo con el lock del runner, tomado antes de los casos.

`Guard::autorizarFixtures` verifica configuración de base, consentimiento, cuenta karma_caja_it,
sesión real con DATABASE()/VERSION() y marca antes de cada inserción nueva. Se mantiene también
el hook global que revalida la base antes de cada SQL Laravel y la prohibición de reconexiones.

No hay cambio de esquema ni de preparación: se reutilizan **caja_it_runs** y **caja_it_control**.
Una base ya preparada no necesita volver a ejecutar --prepare, migraciones o seeders.
Cada caso anexa fixtures en un bloque disjunto de cuatro años según su id de ejecución,
con año central `3000 + 4 * id`. Se verifica que el bloque esté vacío. Se detiene antes de
reutilizar períodos o superar el rango admitido; una ejecución fallida no se recicla automáticamente.
Los timestamps operativos son DATETIME; se dejan NULL los created_at/updated_at opcionales para
no depender del límite de TIMESTAMP 2038 de MariaDB 10.4. Los relojes controlados no cambian el sistema.

Los cierres/versiones son **fixtures sintéticos insertados directamente**, no cobros ni rectificaciones
reales. Se necesita esto para probar DECIMAL(16,2) máximo sin crear millones de ventas, cuyo esquema
tiene límites menores. El caso de máximos no fabrica instantáneas por esos importes. Los demás
incluyen ventas e instantáneas pequeñas donde corresponde, y una bitácora sintética por cierre.
La prueba certifica consolidación de importes persistidos, no la generación de cierres enormes por
el flujo de cobro. Los once escenarios previos conservan la validación del flujo real y GET_LOCK.
No se modifica el gate, servicios financieros, rutas ni arquitectura P1–P6.

### Ejecución segura posterior desde PowerShell

**Estos comandos son para el operador; Codex no los ejecutó contra MariaDB.**
Usar únicamente cafeteria_caja_integracion ya preparada y la cuenta karma_caja_it restringida
a esa base. Requisitos y permisos son los de las secciones anteriores: PHP 8.3, pdo_mysql,
proc_open, MariaDB 10.4.32 o 10.11.14 con selección coincidente y observación de sesiones propias en PROCESSLIST. No usar root.
Si falta la base o su marca, detenerse y revisar la preparación manual; no reiniciar una base existente.
Si hay configuración Laravel cacheada, detenerse; el arnés no la borra ni cambia .env.

```powershell
Set-Location C:\xampp\htdocs\cafeteria_pro
git branch --show-current
& C:\php83\php.exe -v
& C:\php83\php.exe -m
$env:KARMA_CAJA_ALLOW = 'cafeteria_caja_integracion'
$env:KARMA_CAJA_DB = 'cafeteria_caja_integracion'
$env:KARMA_CAJA_HOST = '127.0.0.1'
$env:KARMA_CAJA_PORT = '3308'
$env:KARMA_CAJA_USER = 'karma_caja_it'
$claveCaja = Read-Host 'Contraseña de karma_caja_it' -AsSecureString
$env:KARMA_CAJA_PASSWORD = [System.Net.NetworkCredential]::new('', $claveCaja).Password
try {
    # Alternativa A: reporte único, 51 ejecuciones (33 anteriores + 18 nuevas).
    & C:\php83\php.exe tests/Integration/CajaMariaDb/run.php --suite=ampliada --case=all --repeat=3
    if ($LASTEXITCODE -ne 0) { throw 'Suite no aprobada. Conservar reporte y mantener bloqueo.' }
} finally {
    Remove-Item Env:KARMA_CAJA_PASSWORD -ErrorAction SilentlyContinue
    $claveCaja = $null
}
```

Alternativa B, con las mismas variables configuradas: dos reportes separados, sin ejecutar también A
a menos que se desee otra ronda completa de fixtures:

```powershell
& C:\php83\php.exe tests/Integration/CajaMariaDb/run.php --suite=concurrencia --case=all --repeat=3
if ($LASTEXITCODE -ne 0) { throw 'Detener: regresión de concurrencia fallida.' }
& C:\php83\php.exe tests/Integration/CajaMariaDb/run.php --suite=consolidados --case=all --repeat=3
if ($LASTEXITCODE -ne 0) { throw 'Detener: integración de consolidados fallida.' }
```

Caso individual:

```powershell
& C:\php83\php.exe tests/Integration/CajaMariaDb/run.php --suite=consolidados --case=conso-decimales --repeat=3
```

El reporte JSON identifica suite y tipo de cada caso nuevo, verificación DECIMAL, integridad y huella.
Los casos anteriores conservan sesiones independientes y evidencia de espera real. Los casos de
consolidados son lecturas secuenciales, no pruebas adicionales de concurrencia; su PASS no atribuye
sesiones o esperas que no ocurrieron. Revisar 33 PASS de concurrencia y 18 PASS de consolidados,
sin omisiones, sin bloqueos retenidos y con `habilita_cierre_produccion: false`.

No borrar fixtures/evidencias automáticamente. Mantener detenida cualquier otra actividad sobre
la base durante la suite: las huellas globales detectarían cambios ajenos. La preparación parcial,
colisión de períodos, diferencia de esquema o fallos requieren revisión manual. Un resultado PASS
no autoriza ni habilita producción. No se ensayan aquí fallos de red ni concurrencia entre una
rectificación y una lectura del consolidado; los once casos anteriores conservan su alcance original.

### Verificación segura durante el desarrollo

`CajaMariaDbConsolidadosFixturesTest` verifica el catálogo, cinco casos de fixtures en SQLite :memory:,
rechazo del máximo DECIMAL en SQLite, marca incorrecta, bloqueo de escrituras y detección de cambios
por PDO directo mediante huellas. No certifica MariaDB ni ejecuta run.php o sus workers.

Resultado de desarrollo de etapa 6.1: suite completa con 202 pruebas, 201 aprobadas, una omitida
por concurrencia real MariaDB pendiente en PHPUnit, 2907 aserciones. Los seis tests nuevos son
seguros en memoria. Los cinco casos de fixtures ejecutados en memoria reportan explícitamente
`decimal_mariadb_verificado: false`; no deben presentarse como PASS de integración MariaDB.

## Adaptación 6.3A — Revalidación del permiso de cobro

VentaController::store ahora autoriza antes de esperar el coordinador y revalida usuario, rol y
cobrar_ventas dentro de la transacción, con orden rol → usuario → permisos, antes de pedido/inventario.
Runtime::prepararAutorizacionActor agrega también **cobrar_ventas** al actor sintético id=1/rol=1
mediante firstOrCreate y syncWithoutDetaching. No se concede por nombre del rol ni se retiran permisos.
Esta preparación idempotente permite reutilizar la base ya marcada, sin repetir --prepare.

Se conserva el catálogo de 17 escenarios y sus comandos. No se ejecutó MariaDB en esta etapa;
los 51 PASS anteriores no certifican la nueva huella. La prueba en memoria revoca el permiso
mediante RolController en una frontera de espera controlada; no equivale a revocación durante
un User lock real. Repetir la regresión aislada antes de continuar la validación integral.
