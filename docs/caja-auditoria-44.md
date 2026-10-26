# Etapa 4.4 — Auditoría de interfaz de caja

Fecha: 2026-10-26. Rama verificada: feature/cierre-caja. Producción continúa bloqueada.
No se abrieron conexiones a MariaDB ni se ejecutaron el arnés de integración, migraciones o seeders
sobre bases persistentes. Las pruebas usaron únicamente SQLite :memory: y PDO simulado.

## Rutas y autorización verificadas

Las ocho rutas pertenecen al grupo web (sesión y CSRF), auth y permiso:consultar_caja:

| Método / ruta | Controlador y método | Permiso adicional |
| --- | --- | --- |
| GET /admin/caja | CajaController::index | — |
| GET /admin/caja/previsualizacion | CajaController::previsualizacion | — |
| GET /admin/caja/cierres/{cierre} | CajaController::show | — |
| GET /admin/caja/cierres/{cierre}/versiones/{version} | CajaController::version | — |
| GET /admin/caja/confirmacion | OperacionesCajaController::confirmacion | confirmar_cierre_caja |
| POST /admin/caja/cierres | OperacionesCajaController::confirmar | confirmar_cierre_caja |
| GET /admin/caja/cierres/{cierre}/rectificacion | OperacionesCajaController::rectificacion | rectificar_cierre_caja |
| POST /admin/caja/cierres/{cierre}/rectificaciones | OperacionesCajaController::rectificar | rectificar_cierre_caja |

GET incluye HEAD implícito de Laravel. No se añadieron ni modificaron rutas durante esta auditoría.
Los identificadores siguen siendo escalares sin binding financiero anticipado.
AutorizacionCaja::consulta/confirmacion/rectificacion se ejecuta antes de los datos financieros;
los servicios repiten la autorización y, tras GET_LOCK, bloquean rol → usuario → cierre/versiones.
No se conceden permisos por nombre del rol. Usuarios/roles inactivos y permisos revocados se rechazan.

Una prueba recorre las ocho rutas sin consultar_caja y verifica 403 sin SELECT financieros ni GET_LOCK.
Otra reemplaza la resolución de ambos servicios por una excepción: con el gate real cerrado, ambos
POST siguen devolviendo 503, demostrando que ni siquiera se resuelven los servicios financieros.
CSRF ausente o inválido produce 419 antes del controlador; la garantía 503 se comprueba con actor
autorizado y CSRF válido. La autenticación existente puede actualizar actividad de sesión, sin alterar
registros financieros. No se cambió su comportamiento.

## Hallazgos reproducidos y correcciones limitadas

### 1. Falso rechazo cuando ValidationException aparece después del commit

Reproducción en memoria: un callback de creación de versión registra DB::afterCommit y este lanza
ValidationException. Antes del ajuste, el controlador respondía «La solicitud fue rechazada», aunque
la versión y su evento confirmado ya existían. La prueba nueva falló antes de corregirlo.

Corrección: estado local por invocación en OperacionesCajaController::segura. Antes de invocar el
servicio se marca la entrada al dominio. Una ValidationException posterior conserva HTTP 422 pero
muestra resultado no confirmado y la advertencia de que la operación podría haberse registrado;
no afirma rollback ni reintenta. La validación HTTP previa conserva el rechazo normal.
La prueba verifica confirmación y rectificación, persistencia real en memoria y un solo evento
confirmado de cada tipo. La auditoría de dominio no se modifica ni se duplica en el controlador.

### 2. Botón Actualizar sobre una respuesta POST

El layout compartido utilizaba window.location.reload() también en respuestas POST de caja.
Ese botón podía solicitar el reenvío del formulario en el navegador. La prueba DOM nueva falló
antes del ajuste. No se observó una evasión del gate ni duplicación financiera por este problema.

Corrección: solo para respuestas POST de admin.caja.confirmar/rectificar, se muestra un enlace GET
a admin.caja.index titulado Consultar estado. El resto del layout conserva su comportamiento.
No se modifica el menú, los paquetes ni la lógica de autenticación.

Archivos modificados: OperacionesCajaController.php, layouts/app.blade.php,
CajaFormulariosTest.php y documentación de formularios. Se agrega este informe.

## Resto de controles revisados

- Gate real de CoordinadorCobrosCaja sigue en false. La excepción de pruebas requiere PHPUnit CLI,
  entorno testing, SQLite realmente en memoria sin archivos adjuntos y coordinador inyectado explícito.
- CSRF sin exclusiones de caja. Los tests desactivan su omisión automática de PHPUnit y comprueban
  token ausente, inválido y válido en ambos POST.
- Lista cerrada de campos, rechazo de totales/actor/diferencia inyectados, dinero por cadenas/centavos,
  fecha de Bolivia, límites de observación/motivo y versión esperada comprobada en el dominio.
- Vistas con Blade escapado. Sin JS de reintento, guardado financiero en navegador o borradores activos.
  Las respuestas financieras se marcan private, no-store. No se devuelven mensajes SQL o trazas.
- Se conservan solo P1–P6; Caja en P4 — Pedidos, Ventas y Caja, id pedidos. El permiso consultar_caja
  por sí solo muestra sus opciones. Otras opciones siguen filtrándose independientemente.
- Detalles desde instantáneas; versión buscada dentro de su cierre. Históricos NULL no se oficializan.
- No hay PUT/PATCH/DELETE para cierres, versiones o bitácoras. Las pruebas verifican 405. El controlador
  antiguo sigue sin rutas. HistorialFinanciero y su builder bloquean reescritura/eliminación Eloquent.

## Pruebas ejecutadas

- Suite completa: 178 pruebas, 177 aprobadas, una omitida, 2433 aserciones.
- La omitida exige MariaDB real; no se presenta SQLite como prueba de concurrencia real.
- Edge headless: 40/40 combinaciones (10 pantallas, 320/375/768/1440 píxeles), archivos HTML sintéticos
  y solicitudes interceptadas localmente. Sin red real, sin Laravel vivo y sin datos personales.
- Sintaxis PHP correcta en controlador, pruebas y los cuatro PHP del arnés MariaDB.
- git diff --check correcto (avisos de normalización CRLF/LF existentes, sin errores de whitespace).

## Compatibilidad estática del arnés MariaDB

Se mantienen 11 casos: dos-cobros, cobro-y-cierre, cierres-duplicados, cobro-durante-cierre,
medianoche, timeout, rollback, mismo-pedido, fecha-cerrada, rectificaciones-duplicadas,
rectificacion-y-cobro. El runner y dos workers conservan sesiones independientes, barreras y
observación PROCESSLIST de espera User lock. Las firmas de los servicios no cambiaron.

Runtime llama directamente a VentaController::store y a los servicios de cierre/rectificación;
no usa OperacionesCajaController ni DisponibilidadCajaWeb. El adaptador de integración permite
operar solo en la base desechable marcada y utiliza el coordinador MariaDB real. La interfaz web
nueva y las correcciones de esta revisión no alteran ese protocolo. Guard sigue verificando
DATABASE(), VERSION() y sesión antes de cada SQL Laravel, impidiendo reconexión y nombres protegidos.

La huella del arnés cubre dominio, modelos financieros, cobro y archivos de integración, pero no
todos los controladores/vistas web. Un PASS del arnés no certifica por sí solo la capa HTTP.
Los 33/33 PASS aportados previamente por el operador permanecen como referencia histórica;
no se ejecutó el arnés ni se comprobó aquí un nuevo resultado real o la huella de aquel reporte.

## Repetición aislada recomendada (ejecución posterior por el operador)

1. Revisar rama, PHP 8.3, pdo_mysql y proc_open. Conservar cambios locales y reportes anteriores.
2. Usar exclusivamente cafeteria_caja_integracion, MariaDB 10.4.32 local y cuenta karma_caja_it,
   sin privilegios sobre cafeteria_pro o cafeteria_pro_pruebas. No usar root para el runner.
3. Si ya está preparada y marcada, reutilizarla sin --prepare: el runner agrega fixtures con fechas
   nuevas. Si no existe o está incompleta, detenerse y seguir la preparación manual de
   tests/Integration/CajaMariaDb/README.md en una base nueva desechable, con autorización separada.
   No usar migrate:fresh, DROP, TRUNCATE ni reparaciones automáticas.
4. En PowerShell, sin modificar .env, configurar las variables explícitas y ejecutar:

```powershell
Set-Location C:\xampp\htdocs\cafeteria_pro
git branch --show-current
& C:\php83\php.exe -v
$env:KARMA_CAJA_ALLOW = 'cafeteria_caja_integracion'
$env:KARMA_CAJA_DB = 'cafeteria_caja_integracion'
$env:KARMA_CAJA_HOST = '127.0.0.1'
$env:KARMA_CAJA_PORT = '3308'
$env:KARMA_CAJA_USER = 'karma_caja_it'
$claveCaja = Read-Host 'Contraseña de karma_caja_it' -AsSecureString
$env:KARMA_CAJA_PASSWORD = [System.Net.NetworkCredential]::new('', $claveCaja).Password
try {
    & C:\php83\php.exe tests/Integration/CajaMariaDb/run.php --case=all --repeat=3
    if ($LASTEXITCODE -ne 0) { throw 'Regresión no aprobada. Conservar evidencias y mantener bloqueo.' }
} finally {
    Remove-Item Env:KARMA_CAJA_PASSWORD -ErrorAction SilentlyContinue
    $claveCaja = $null
}
```

5. Exigir 33/33 PASS sin omisiones, tres sesiones distintas, espera real y ausencia de locks retenidos.
   Conservar report.json y código_sha256; revisar cualquier FAIL y la huella del código probado.
   Si hay configuración cacheada o una base distinta, detenerse: no cambiar configuración para forzar.
6. Un PASS no cambia el gate. No habilitar producción sin una autorización posterior explícita.

## Riesgos pendientes y límites

- El estado HTTP no es una prueba de commit/rollback. La advertencia conservadora tras invocar el
  servicio puede aparecer también en un rechazo real; reconciliar versión y auditoría antes de reenviar.
- F5 o reenvío manual del navegador aún pueden repetir un POST. No hay reintentos automáticos de la
  aplicación; fecha única y versión esperada impiden repetir la escritura financiera. Un flujo PRG
  general de errores sería una mejora posterior que requiere diseño/autorización, no aplicada aquí.
- SQL directo, DB::table y toBase eluden las protecciones Eloquent. Son un límite de privilegios de BD,
  no accesible mediante las rutas de caja revisadas. No se añadieron triggers o permisos SQL.
- Sin conexión/actor, incidentes usan logs; si también falla su almacenamiento no hay garantía de
  durabilidad. Mantener supervisión y procedimiento manual para incertidumbre de commit.
- Ensayar pérdida real de conexión, cookies/sesión/HTTPS y CSRF de navegador en un entorno aislado
  antes de activar. Las pruebas visuales con HTML no sustituyen ese ensayo.

Conclusión: controles de interfaz verificados dentro del alcance, dos defectos corregidos y
producción bloqueada. Sin cambios de servicios, transacciones, GET_LOCK, auditoría o esquema.
