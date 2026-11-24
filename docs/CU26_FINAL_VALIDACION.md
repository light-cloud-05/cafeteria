# CU26 — Cierre de implementación local y validación

Fecha de revisión: 23 de noviembre de 2026, America/La_Paz.

**Estado: IMPLEMENTADO CON PRUEBAS PENDIENTES.** El alcance es una simulación local
asistida por el cajero. No existe conexión con WizPay, su Sandbox, bancos ni dinero
real. No se ha modificado el informe Word universitario.

## Arquitectura y persistencia

La interfaz Blade llama a PagosSimuladosController. Sus rutas exigen autenticación,
permiso cobrar_ventas, CSRF para POST y aislamiento verificado. El controlador solo
admite campos conocidos; no acepta monto, estado definitivo o datos de tarjeta.

IntentosPagoService crea y resuelve intentos bajo la coordinación de caja existente.
EscenarioSimulado define una lista cerrada de escenarios compatibles con el medio.
FinalizarCobroService relee autorización, pedido y versión, comprueba el importe
persistido y finaliza tanto cobros presenciales como simulados. La aprobación,
venta, asociación, estado Pagado y auditoría se guardan en una única transacción.

Migraciones del CU26 (nuevas en la fase 1, sin editar las anteriores):

- 2026_11_23_000001_agregar_tarjeta_y_origen_a_ventas_y_caja.php: monto_tarjeta y
  origen en ventas/instantáneas; tarjeta_registrado en versiones. Históricos: cero
  tarjeta y origen OPERACION, sin reescribir efectivo, QR, total o cambio.
- 2026_11_23_000002_crear_transacciones_pago.php: pedido, responsable, venta nullable,
  referencia, clave de idempotencia, medio, origen SIMULACION_LOCAL, monto, BOB,
  estado, versión, vencimiento UTC, confirmación, resolución y código de resultado.
  Referencia/clave/venta asociada son únicas. Una columna generada y única permite
  un solo intento PENDIENTE por pedido y conserva varios terminales históricos.

Las relaciones restringen eliminaciones de historial. Los rollback nuevos rechazan
pérdida de intentos o importes/origen simulados ya registrados.

## Estados y flujos

PENDIENTE puede pasar a APROBADO, RECHAZADO, EXPIRADO o CANCELADO. Los terminales no
se reprocesan: una solicitud repetida devuelve el resultado ya guardado.

QR: elegir pedido Registrado, confirmar el total calculado en servidor, crear el
intento y seleccionar un escenario local. La representación no es escaneable ni
contiene datos bancarios. Tarjeta: seleccionar un identificador ficticio aprobado,
rechazado o 3DS pendiente. 3DS es solo una simulación explicativa: no se solicitan
códigos ni se afirma que un banco autenticó a alguien.

APROBADO crea una venta y marca Pagado. Los otros estados no crean ventas. La
expiración natural se resuelve por POST al consultar/procesar; GET es de solo
lectura. EXPIRACION_DEMO permite simular un vencimiento anticipado, explícitamente
identificado, sin cambiar reloj o fecha original de vigencia.

La clave y la preparación se conservan en sesión. Un reintento explícito tras un
terminal tiene otra clave. Tras errores de comunicación se consulta primero el
intento original por referencia o la solicitud propia por clave. Una ausencia
observada no prueba rollback y no inicia automáticamente otro cobro.

La reserva de inventario se efectúa al registrar el pedido. Aprobar no descuenta
otra vez. Rechazar/cancelar/expirar el intento conserva la reserva del pedido;
cancelar el pedido después de resolver el intento devuelve existencias. Pendientes
bloquean modificaciones, cancelación del pedido y cobros incompatibles.

## Seguridad y separación

PAGOS_SIMULACION_ACTIVA es false por defecto. Para operar se exige la guarda de demo:
entorno demostracion, base cafeteria_defensa_demo, cuenta karma_defensa_demo,
TCP 127.0.0.1, puerto y servidor esperados, sin URL alternativa/persistencia/roles
SQL ni privilegios fuera de la base. La cuenta de ejecución tiene solo DML. La
guarda se conserva antes de las escrituras y en la coordinación. La excepción de
pruebas requiere PHPUnit en CLI y SQLite exclusivamente :memory:.

La lectura también está protegida. ContextoLecturaPagos impide mostrar ventas o
instantáneas simuladas en reportes, métricas de inicio, listados/detalle de pedidos,
ventas y caja cuando no puede acreditarse el contexto autorizado. No se filtran
silenciosamente las ventas ficticias para presentar un total parcial. Se bloquea
la consulta. Tampoco se cambian sus importes ni origen para permitir el acceso.

Los permisos de reportes y caja siguen siendo independientes del permiso de cobro.
No se otorgaron permisos nuevos a roles existentes. Solo el responsable original
puede resolver/cancelar un intento; cajeros autorizados pueden leer su historial
compartido. La auditoría de pagos usa campos explícitos no sensibles. No se guardan
PAN, CVV, vencimientos, payloads bancarios o credenciales del proveedor.

## Reportes e integración con caja

VentasReporteService usa BigDecimal sobre representaciones decimales para acumular
total, efectivo, QR y tarjeta. ImporteReporteVista formatea cadenas sin convertir
acumulados a float. Comprueba total = efectivo + QR + tarjeta y consistencia de
método/origen. Ante incoherencias no presenta totales parciales ni altera datos.

Los reportes diario y por método incluyen fecha boliviana, pedido, método, importe,
componentes, origen y total general. Cada venta Mixto ocupa una fila de resumen:
sus componentes no cuentan como ventas adicionales. Transferencia QR conserva su
importe histórico y se presenta como QR manual. Solo se consultan ventas
Completado; los intentos fallidos/pendientes nunca se suman como ventas.

Los reportes y pantallas de demostración muestran:
**SIMULACIÓN LOCAL — SIN DINERO REAL**. OPERACION identifica el canal presencial
histórico; en la base ficticia no significa que los fixtures muevan dinero real.

CierreDiarioService incluye tarjeta en cálculo e instantáneas. Las rectificaciones
conservan los componentes y origen, añadiendo una versión. ReconciliacionCajaService
verifica instantáneas y eventos; los eventos antiguos sin tarjeta equivalen a cero
sin reescribirlos. ConsolidacionCajaService suma solo versiones vigentes y conserva
una única lectura para el período, incluida la detección de instantáneas simuladas.
Listado, previsualización, confirmación, versión y consolidados presentan tarjeta.
**CoordinadorCobrosCaja normal sigue bloqueando cierres definitivos productivos.**

## Entorno comprobado (consultas sin modificar bases)

- Laravel 13.38.0, confirmado con Artisan.
- PHP 8.3.36 en C:/php83/php.exe. El comando php sigue apuntando a PHP 8.1.25.
- PDO MySQL/SQLite y extensiones de ejecución comprobadas.
- Composer check-platform-reqs --no-dev con PHP 8.3: requisitos satisfechos.
- Rutas de CU26 y reportes comprobadas con route:list.
- .env operativo: entorno local, driver mysql, destino local y simulación apagada.
  No se publicaron credenciales ni se modificó ese archivo.
- Consulta PDO a MySQL local: falló con código 2002.
- No existe .env.demostracion y no hay bootstrap/cache/config.php.
- No es posible identificar el esquema instalado ni sus migraciones pendientes
  mientras la conexión y la configuración aislada no estén disponibles.

No se iniciaron servicios MySQL ni se crearon bases/cuentas. No se ejecutaron
migraciones, seeders, preparadores o pruebas concurrentes en una base persistente.

## Preparación manual necesaria y comandos Windows 11

Actualización de preparación local: ya existe un procedimiento exclusivo revisable
en [INSTALACION_ESQUEMA_DEMO.md](INSTALACION_ESQUEMA_DEMO.md). Mantiene separadas las
cuentas de instalación y ejecución y no requiere debilitar las guardas del runtime.
El diagnóstico anterior de este documento se conserva como registro histórico.

Primero un operador debe preparar una instancia MariaDB exclusivamente ficticia y
bajo control local, sin túneles ni proxies. Seguir las condiciones completas de
[DEMOSTRACION_CAJA_LOCAL.md](DEMOSTRACION_CAJA_LOCAL.md).

Falta concretamente:

1. Un servidor MariaDB local accesible y su puerto/hostname verificados.
2. Base nueva cafeteria_defensa_demo, sin datos copiados de la operación.
3. Cuenta de instalación separada y limitada a esa base para instalar las
   migraciones actuales. La cuenta de ejecución no debe recibir privilegios DDL.
4. Cuenta nueva karma_defensa_demo con los grants DML exactos de la guía. Si los
   nombres ya existen, detenerse y revisar su origen, sin reutilizarlos a ciegas.
5. Esquema instalado con tablas InnoDB y sin objetos externos añadidos. El operador
   debe verificar vistas, triggers, rutinas y eventos con su cuenta de instalación.
6. .env.demostracion separado, con APP_KEY nueva exclusiva, APP_ENV=demostracion,
   CAJA_DEMO_ACTIVA=true, CAJA_DEMO_SERVIDOR verificado, DB_CONNECTION=mysql,
   DB_HOST=127.0.0.1, puerto explícito, DB_DATABASE=cafeteria_defensa_demo y la cuenta
   runtime limitada. Sin DATABASE_URL ni DB_SOCKET alternativos. No editar .env.
7. PAGOS_SIMULACION_ACTIVA=true y PAGOS_SIMULACION_RESULTADO=PENDIENTE. La cuenta
   de interfaz ficticia requiere cobrar_ventas; para informes, ver_reportes;
   consultar_caja y demás permisos de caja son independientes.

Antes de instalar, el operador debe consultar en la sesión SQL efectiva DATABASE(),
CURRENT_USER(), USER(), @@hostname, @@port, CURRENT_ROLE() y SHOW GRANTS, sin compartir
salidas que contengan hashes/credenciales. Comprobar destino, permisos, ausencia de
datos y motor del esquema. No se proporciona un migrate genérico contra la
configuración operativa desconocida ni se debe usar migrate:fresh/db:wipe/refresh.
La instalación con DDL necesita un procedimiento separado y verificado; el runtime
de demostración rechaza deliberadamente una cuenta con esos privilegios.

Una vez instalado y verificado el esquema:

```powershell
Set-Location C:\xampp\htdocs\cafeteria_pro
& C:\php83\php.exe -v
& C:\php83\php.exe artisan --version
& C:\php83\php.exe artisan route:list --path=admin/pagos-simulados
& C:\php83\php.exe artisan route:list --path=admin/reportes
if (-not (Test-Path -LiteralPath '.env.demostracion')) {
    throw 'Falta la configuración aislada de demostración. No iniciar con el .env operativo.'
}
& C:\php83\php.exe artisan migrate:status --env=demostracion
```

Si falta esquema, detenerse y resolverlo con la cuenta de instalación. migrate:status
es de consulta; no instala nada. Para cargar fixtures solo en el esquema demo vacío
y verificado, el preparador existente requiere aceptación expresa:

```powershell
& C:\php83\php.exe artisan caja:preparar-demo --env=demostracion --acepto-base=cafeteria_defensa_demo
& C:\php83\php.exe artisan serve --env=demostracion --host=127.0.0.1 --port=8013
```

El preparador no borra datos y rechaza tablas ya pobladas. No ejecutarlo de nuevo
para restablecer contraseñas ni para reiniciar una demostración existente. Guardar
la contraseña ficticia que presenta fuera de capturas y documentación compartida.

Abrir http://127.0.0.1:8013. No publicar el servidor de demostración en la red.
Estas pantallas usan Blade y assets de public; no necesitan npm para mostrarse.

## Guion manual pendiente en navegador real

1. Iniciar sesión con defensa.demo o una cuenta ficticia autorizada. Comprobar el
   aviso de demostración. Anotar los totales actuales de hoy como referencia.
2. Crear un pedido ficticio desde Nuevo pedido (la cuenta demo necesita ese permiso).
   Abrir Ventas y cobros, seleccionar el pedido y confirmar su total.
3. Elegir QR simulado, crear el intento, anotar referencia y seleccionar QR de
   prueba aprobado. Comprobar APROBADO, una venta, pedido Pagado y stock sin un
   segundo descuento. Actualizar y repetir la consulta: no aumenta la venta.
4. Abrir el historial: verificar referencia, BOB, importe, fechas y SIMULACION_LOCAL.
5. Crear otro pedido. Ejecutar tarjeta rechazada: no debe aparecer venta ni Pagado.
   Preparar nuevo intento y aprobar tarjeta: debe aparecer una sola venta nueva.
6. Crear otro pedido y elegir 3DS ficticio pendiente. Comprobar que no hay venta,
   no se solicitan códigos y otro cobro/edición del pedido queda bloqueado.
7. Cancelar ese intento y comprobar la traza. Preparar otro, esperar la vigencia y
   consultar por POST para EXPIRADO, o usar expiración ficticia claramente marcada.
8. En reportes de hoy verificar métodos, componentes y origen. La variación total
   debe coincidir únicamente con los pedidos aprobados, sin sumar rechazos o 3DS.
   Efectivo + QR + tarjeta debe coincidir con el total; cambio no es ingreso.
9. Probar también Efectivo, QR manual y Mixto con pedidos diferentes. Mixto ocupa
   una sola venta y una sola fila de método en el resumen.
10. Abrir previsualización de caja y comprobar tarjeta. No habilitar cierres
    productivos; los cierres demo conservan sus requisitos de día terminado.
11. Repetir a ancho móvil, desplazando las tablas hasta sus últimas columnas.
12. Ante error de comunicación, consultar primero el intento original por referencia
    o clave, sin generar otro. No usar datos ni cuentas operativas para provocar fallos.

No desactivar la simulación dejando pendientes sin resolver. Su bloqueo de dominio
se conserva aunque las pantallas estén apagadas; no editar estados por SQL.

## Criterios y límites de la validación

| Criterio | Estado y evidencia |
|---|---|
| QR/tarjeta y estados | Verificados en pruebas de dominio y HTTP SQLite |
| Una venta por aprobación e idempotencia | Verificados, incluido error después de commit ficticio |
| Rechazo/pendiente/expiración/cancelación sin venta | Verificados |
| Stock y cobros presenciales | Regresión verificada |
| Reportes y precisión | Verificados; Mixto una vez, centavos exactos e históricos conservados |
| Caja y tarjeta | Verificados en SQLite, con rectificación/reconciliación/consolidados |
| Permisos, CSRF y aislamiento | Verificados en pruebas; configuración real demo pendiente |
| Trazabilidad | Intentos, ventas y versiones comprobados |
| DDL y concurrencia MariaDB | PENDIENTE: servicio inaccesible y demo sin configurar |
| Recorrido real de navegador sobre Laravel/MariaDB | PENDIENTE: no hay entorno seguro instalado |
| Adaptación visual | HTML sintético renderizado en Edge; no equivale al recorrido real |

## Requisitos antes de GitHub/AWS

No se accedió al remoto ni a AWS. No hubo SSH, commits, pushes o despliegues.

Antes de publicar: revisar el conjunto completo de cambios locales, resolver las
validaciones pendientes de MariaDB/navegador, excluir credenciales y artefactos de
pruebas, conservar el lock y disponer de un respaldo y plan de reversión aprobado.

En EC2 Ubuntu se deberá comprobar posteriormente la versión realmente instalada:
el código exige PHP ^8.3 y Laravel 13.38.0. No se ha verificado qué PHP tiene AWS;
una instalación PHP 8.1 sería incompatible. Verificar PHP CLI y FPM, extensiones,
Composer, permisos de storage/bootstrap/cache y document root public.

Usar composer.lock sin actualizar dependencias. Las dos migraciones del CU26 y
todas las anteriores pendientes deben revisarse contra el esquema real antes de
aplicarlas. No deshacer migraciones con historia de pagos: sus guardas lo impiden.

Producción: APP_ENV=production, APP_DEBUG=false, PAGOS_SIMULACION_ACTIVA=false,
CAJA_DEMO_ACTIVA=false, sin configuración/usuarios/base demo publicados, sin HTML de
fixtures, perfiles ni capturas del arnés. Regenerar cachés solo bajo configuración
correcta cuando se autorice el despliegue. Los cierres productivos siguen bloqueados.
Una integración oficial futura requiere documentación y credenciales independientes.

## Archivos de la tercera fase

Nuevos: app/Services/Pagos/ContextoLecturaPagos.php,
app/Services/Reportes/VentasReporteService.php, app/Support/ImporteReporteVista.php,
resources/views/reportes/{_contexto,_ventas,error}.blade.php,
tests/Feature/CU26ReportesTest.php y este documento.

Modificados: controladores Reportes, Inicio, Venta, Pedido y Caja; servicios de caja
CierreDiario, Reconciliacion y Consolidacion; vistas de reportes diario/por método,
welcome, ventas/index, pagos/_aviso y caja (listado, previsualización, confirmación,
versión, resumen mensual/anual); exportación de PagosSimuladosWebTest y arnés
pagos-responsive.cjs. Las guías anteriores enlazan este cierre técnico.
También se ajustaron pedidos/show y pagos/show para evitar avisos/controles
duplicados conservando la identificación de los pagos ficticios.

## Verificación final completada tras la interrupción

La ejecución anterior se interrumpió porque la revisión automática no pudo
completarse por límite de uso. No fue una declaración de que la prueba fuera
insegura. Al reanudar se conservó el árbol de trabajo y se repitió la comprobación
con autorización, sin comandos de borrado ni cambios de variables del entorno.

- PHP 8.3.36, PHPUnit 12.5.40.
- Comando final: `C:/php83/php.exe vendor/bin/phpunit --do-not-cache-result --display-skipped`.
- Resultado: **298 pruebas, 3.976 aserciones, cero errores, cero fallos, una omitida**.
- Omitida: `test_concurrencia_real_cobro_cierre_requiere_integracion_y_mariadb`.
- Exportación final de vistas: dos pruebas y 55 aserciones aprobadas, usando una
  copia temporal de phpunit.xml que conserva SQLite :memory:. No se modificó la
  configuración del proyecto ni variables del proceso de trabajo para exportar.
- Arnés Edge: **60 comprobaciones aprobadas**, 15 vistas a 320/375/768/1440 px,
  incluyendo codificación, aviso, importes, CSRF y ausencia de desbordamiento.
  Todas las solicitudes de página se interceptaron y sirvieron desde archivos
  locales. No es un recorrido real contra Laravel/MariaDB.
- Captura del reporte por método inspeccionada después de la corrección: textos
  UTF-8 correctos, cinco métodos, desglose y total general de fixtures Bs 123.50.
- Sintaxis PHP verificada en 33 archivos nuevos/modificados; JavaScript del arnés
  y `git diff --check` correctos. Los archivos inspeccionados son UTF-8 válido sin
  caracteres de reemplazo.
- .env.demostracion sigue ausente. No se ejecutaron migraciones persistentes,
  preparadores o seeders; tampoco commits, pushes, SSH o despliegues.

La implementación local queda terminada según las pruebas disponibles. La
clasificación sigue siendo **IMPLEMENTADO CON PRUEBAS PENDIENTES** por la ausencia
de MariaDB aislada verificable y del recorrido manual real en navegador.
