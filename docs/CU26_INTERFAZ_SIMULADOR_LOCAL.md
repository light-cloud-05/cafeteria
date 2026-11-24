# CU26 — Interfaz y escenarios locales (Prompt 2)

El estado de la tercera fase, reportes y validaciones pendientes está en
[CU26_FINAL_VALIDACION.md](CU26_FINAL_VALIDACION.md). Este documento conserva el
alcance y los resultados de la segunda fase.

Simulación académica asistida por el cajero. No hay conexión con WizPay, Sandbox,
bancos, servicios de generación de QR ni dinero real. No se solicitan PAN, CVV,
vencimientos de tarjeta o códigos bancarios.

## Archivos de esta fase

Nuevos:

- app/Http/Controllers/PagosSimuladosController.php
- app/Http/Middleware/SimulacionPagoDisponible.php
- app/Services/Pagos/EscenarioSimulado.php
- app/Support/DisponibilidadPagosSimulados.php
- resources/views/pagos/{_aviso,_estilos,_acceso,crear,show,historial,recuperacion}.blade.php
- public/css/pagos-demo.css
- public/js-pagos-demo.js
- tests/Feature/PagosSimuladosWebTest.php
- tests/Browser/pagos-responsive.cjs
- docs/CU26_INTERFAZ_SIMULADOR_LOCAL.md

Modificados:

- app/Services/Pagos/IntentosPagoService.php
- routes/web.php
- resources/views/ventas/form.blade.php
- resources/views/ventas/index.blade.php
- resources/views/pedidos/show.blade.php
- docs/CU26_BASE_SIMULACION_LOCAL.md (referencia a esta segunda fase)

No hay nuevas migraciones, dependencias, cambios de permisos, cambios de versión ni
modificaciones de credenciales. Los cambios de caja anteriores se conservan.

## Rutas y contratos

Todas las rutas usan auth, permiso:cobrar_ventas y SimulacionPagoDisponible.
No son webhooks ni endpoints públicos del proveedor.

| Método | URL bajo /admin | Nombre admin.pagos.* | Función |
|---|---|---|---|
| GET | pedidos/{id}/pago-simulado | crear | Preparar importe y clave; no crea pagos |
| POST | pedidos/{id}/pago-simulado | store | Crear intento pendiente |
| GET | pagos-simulados | historial | Historial paginado; filtro pedido opcional |
| GET | pagos-simulados/recuperar | recuperar | Buscar la solicitud propia por clave |
| GET | pagos-simulados/{referencia} | show | Estado guardado y resultado, HTML o JSON |
| POST | pagos-simulados/{referencia}/consultar | consultar | Resolver resultado configurado o vencimiento |
| POST | pagos-simulados/{referencia}/escenario | resolver | Ejecutar un escenario cerrado de demo |
| POST | pagos-simulados/{referencia}/cancelar | cancelar | Cancelar un pendiente |

Las operaciones POST conservan CSRF real. GET nunca aprueba, expira ni cancela.
Las respuestas de estas rutas no se almacenan en caché.

El inicio recibe únicamente medio, version_pedido y clave_idempotencia. La clave se
conserva en sesión durante la preparación; recargar no genera otra solicitud. El
reintento explícito después de un terminal usa otra preparación, también estable al
recargar. Antes de preparar se consulta si la clave original ya tiene un intento.
Si no existe y el pedido cambió, se presenta el importe actual para confirmación con
la misma clave aún no utilizada. El servicio vuelve a verificar todo al escribir.

Se rechazan campos adicionales y entradas mal formadas con 422, sin flashear
posibles datos bancarios. La solicitud no admite monto, estado, venta_id o usuario_id.
La confirmación de venta sigue a cargo de FinalizarCobroService dentro de la misma
transacción del servicio de intentos.

## Escenarios y flujo

Se conserva procesar(intentoId), que usa configuración del servidor. Se añade
simular(intentoId, EscenarioSimulado), sin modificar configuración global por request.
El enum se valida contra el medio persistido dentro de la transacción coordinada.
La misma operación verifica permisos, aislamiento, pedido, versión e importe.

| Identificador de demostración | Resultado |
|---|---|
| QR_DEMO_APROBADO | APROBADO |
| QR_DEMO_RECHAZADO | RECHAZADO |
| QR_DEMO_PENDIENTE | PENDIENTE |
| TARJETA_DEMO_APROBADA | APROBADO |
| TARJETA_DEMO_RECHAZADA | RECHAZADO |
| TARJETA_DEMO_3DS_PENDIENTE | PENDIENTE |
| EXPIRACION_DEMO | EXPIRADO ficticio, incluso antes del vencimiento previsto |

La cancelación es una acción POST específica que usa cancelar(intentoId). El
vencimiento natural sigue teniendo prioridad al resolver un intento vencido. El
escenario EXPIRACION_DEMO no cambia el reloj ni la fecha de vigencia original y se
identifica expresamente como forzado en la vista y código de resultado.

La representación de QR no es escaneable. La tarjeta es una ilustración CSS sin
datos bancarios. 3-D Secure es únicamente un escenario pendiente con explicación
visible; no hay pantalla bancaria, código de autenticación ni afirmación de que un
banco haya autenticado el pago.

Un cajero autorizado puede consultar el historial compartido. Solo el responsable
original del intento puede resolverlo o cancelarlo. Los terminales permanecen
inmutables; repetir una resolución consulta el resultado existente. Las vistas
ocultan acciones incompatibles y el dominio mantiene los bloqueos independientemente
de la interfaz. El cierre productivo continúa deshabilitado.

Efectivo, QR manual y Mixto conservan su formulario. No se envían como escenarios de
simulación y no usan el endpoint del CU26. Stock, venta única, reserva y auditoría
conservan los contratos de la primera fase.

## Errores y recuperación

No hay reenvíos automáticos, sondeo mutante ni generación de claves desde JavaScript.
JavaScript solo previene el doble envío visual; los formularios también funcionan
sin él. La base y el dominio son los responsables de la idempotencia.

Si la coordinación/consulta falla durante una escritura, se muestra 503 con una
consulta del mismo intento por referencia o de la misma solicitud por clave. No se
publican mensajes SQL/PDO, credenciales o detalles internos. La recuperación por
clave se limita al responsable autenticado. Una ausencia observada devuelve 409 y
no ofrece generar otro pago: no se interpreta como prueba de rollback.

Si el aislamiento no puede acreditarse, las rutas no están disponibles y los
accesos no se muestran. Nunca debilitar la guarda para conseguir que aparezca la UI.

## Visualización local (requiere preparación operativa independiente)

1. Usar C:/php83/php.exe. El PHP 8.1 predeterminado no es compatible.
2. Disponer de una instancia MariaDB local exclusivamente ficticia. Verificar base,
   servidor, puerto y cuenta/grants según docs/DEMOSTRACION_CAJA_LOCAL.md. Aplicar el
   esquema con una cuenta de instalación independiente, tras verificar el destino;
   esta fase no ejecutó migraciones sobre MariaDB ni preparó datos operativos.
3. Usar la configuración de demostración aislada. Deben estar activos
   CAJA_DEMO_ACTIVA=true y PAGOS_SIMULACION_ACTIVA=true; mantener
   PAGOS_SIMULACION_RESULTADO=PENDIENTE para que la consulta no apruebe por defecto.
   Completar los demás requisitos de la demo existente sin reutilizar credenciales
   de producción. No se editó ningún .env en esta fase.
4. Con esquema y datos ficticios preparados, arrancar:

   `C:/php83/php.exe artisan serve --env=demostracion --host=127.0.0.1 --port=8013`

5. Acceder a http://127.0.0.1:8013 con la cuenta ficticia autorizada, abrir Ventas y
   cobros y seleccionar un pedido Registrado. Elegir QR simulado o Tarjeta simulada,
   confirmar el importe, crear el intento y ejecutar un escenario de demostración.
6. Consultar resultado e historial. No usar pedidos/base operativos para este guion.

No se necesita npm para estas pantallas: utilizan Blade y assets locales de public.

## Pruebas reproducibles

PHPUnit fuerza SQLite :memory: y las pruebas nuevas lo comprueban antes de preparar
tablas. CSRF se activa realmente mediante el mismo patrón de CajaFormulariosTest.

`C:/php83/php.exe vendor/bin/phpunit --do-not-cache-result --display-skipped`

El arnés visual usa Node >=22 y Edge instalado, sin descargar paquetes ni navegadores.
Exportar HTML sintético a un directorio existente bajo el temporal del sistema con
CU26_VISTAS_DIR al ejecutar la prueba
test_vistas_renderizadas_y_exportacion_opcional_de_fixtures_sinteticos. Después:

`node tests/Browser/pagos-responsive.cjs <carpeta-temporal-de-fixtures>`

El arnés usa un perfil temporal separado y CDP local, intercepta todas las solicitudes
de página y sirve archivos permitidos desde fixtures/public. No inicia Laravel,
consulta bases ni accede a bancos. Comprueba cinco pantallas a 320/375/768/1440 px,
visibilidad de aviso/importe/CSRF y ausencia de desbordamiento horizontal.

## Pendiente del Prompt 3

Completar presentación de reportes y desglose de tarjeta, revisar etiquetas de origen
en todas las consultas financieras, preparar el guion final y ejecutar integración
real de MariaDB en una base desechable autorizada. También queda el recorrido HTTP
manual sobre la demo instalada. SQLite y los renderizados sintéticos no certifican
concurrencia InnoDB, DDL real ni fallos reales de transporte.

La conexión auténtica a WizPay Sandbox sería otro trabajo con documentación oficial
y credenciales, sin presentar estos resultados locales como respuestas del proveedor.

## Resultados reales de esta fase

- PHP 8.3.36, PHPUnit 12.5.40.
- Suite final: 287 pruebas, 3.837 aserciones, cero fallos y cero errores; una omitida:
  test_concurrencia_real_cobro_cierre_requiere_integracion_y_mariadb.
- Se incorporaron 21 casos de interfaz/HTTP, incluidos escenarios, CSRF real,
  permisos, aislamiento, reintentos y recuperación después de un commit ficticio
  seguido de un error de comunicación.
- Arnés responsive: PASS en 20 combinaciones de pantalla/ancho. Capturas desktop y
  móvil inspeccionadas; solo HTML sintético y recursos locales.
- Sintaxis PHP de siete archivos de esta fase, sintaxis de ambos JavaScript y
  git diff --check correctos.
- PHPUnit y Edge headless necesitaron ejecución fuera del sandbox; se mantuvieron
  SQLite en memoria y la interceptación local de solicitudes del arnés visual.
- Sin instalaciones, migraciones en bases persistentes, modificaciones de .env,
  commits, pushes, conexiones bancarias ni despliegues.

Resolver los pendientes antes de deshabilitar la simulación: el dominio sigue
bloqueando cobros incompatibles aunque sus pantallas estén desactivadas. No alterar
manualmente transacciones para eludir ese bloqueo; reactivar solo en el entorno
aislado verificado y resolver mediante el flujo autorizado.
