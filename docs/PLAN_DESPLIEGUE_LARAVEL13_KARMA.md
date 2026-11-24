# Plan técnico de despliegue y recuperación de KARMA Cafetería

Fecha: 27 de octubre de 2026, America/La_Paz. **Estado: NO-GO para producción; cierres definitivos bloqueados.** Este documento propone trabajo futuro, no concede autorización ni acredita ejecución de sus comandos.

## 1. Alcance y evidencia

Esta revisión solo leyó archivos locales, metadatos Git y documentación oficial pública. No ejecutó PHP, Artisan, Composer, pruebas, migraciones, seeders, consultas a BD ni comandos AWS. No leyó credenciales ni modificó código o configuraciones. La única creación es este documento.

| Condición | Evidencia y alcance |
| --- | --- |
| Rama y revisión local | Verificados `feature/cierre-caja`, `37c0c9a`; árbol limpio antes de crear este documento. La identidad completa del artefacto AWS sigue pendiente. |
| Dependencias locales | `composer.json`: PHP ^8.3, framework ^13.0, Sanctum ^4.0, Tinker ^3.0, PHPUnit ^12.0. `composer.lock`: Laravel v13.38.0, Sanctum v4.3.3, Carbon 3.15.1, Symfony HTTP/Console v7.4.21. El lock no demuestra el vendor instalado en AWS. |
| Estructura | Conserva `bootstrap/app.php` con kernels HTTP/Console y handler propios. No es necesario reemplazar automáticamente la estructura por la de un proyecto nuevo. |
| Pruebas | Usuario y `docs/cobros-autorizacion-63a.md`: 228 totales, 227 aprobadas, 1 omitida, 3.156 aserciones. No repetidas aquí. `phpunit.xml` fuerza SQLite en memoria y sesiones array: no certifica sesiones file, FPM, Nginx ni concurrencia InnoDB. |
| MariaDB histórico | 51/51 PASS sobre 10.4.32, aportados por el operador. La documentación advierte que no certifican la huella posterior de autorización de cobros ni recuperación real. |
| Gate | Verificado `CoordinadorCobrosCaja::permiteCierreDefinitivo()` devuelve `false`. Se conserva durante todo este plan. |
| Migraciones | 26 archivos locales. Registro de las 26 en la restaurada y revisión de tablas/índices/FK: información del usuario, no inspección remota. No demuestra igualdad con producción. |
| AWS | Producción `/var/www/cafeteria` en Laravel 10.50.3; candidato `/home/ubuntu/karma_laravel13_prueba` en 13.38.0; Nginx, PHP 8.3-FPM, MariaDB 10.11.14; 909 MiB RAM, sin swap, 3,3 GB libres: datos aportados, pendientes de verificación operativa. |
| Bases y respaldo | Producción `cafeteria_pro`; restaurada `cafeteria_restauracion_prueba`. SHA-256 del archivo `cafeteria_pre_laravel13_20261026.sql` verificado en AWS/Windows según usuario. No se dispone aquí del hash ni de prueba de restauración actual completa y cronometrada. |

Referencias internas principales: [recuperación y reconciliación](caja-recuperacion-reconciliacion.md), [cobros y precisión monetaria](cobros-autorizacion-63a.md), [autorización](caja-autorizacion.md), [integración MariaDB](../tests/Integration/CajaMariaDb/README.md), [consolidados](caja-consolidados.md), [rectificaciones](caja-rectificaciones.md). Los estados históricos de estos documentos no equivalen a aprobación de la revisión actual. README raíz es el genérico de Laravel y no aporta un procedimiento de despliegue.

## 2. Riesgos concretos y controles

| Riesgo | Hallazgo, consecuencia y condición de aceptación |
| --- | --- |
| Saltar tres versiones mayores | Revisar acumulativamente guías 10→11, 11→12 y 12→13 contra el código y lock exactos. Aprobar una matriz de cambios aplicables/no aplicables con evidencia. Que Composer resuelva no prueba compatibilidad funcional. |
| PHP CLI distinto de FPM | PHP 8.3 cumple el mínimo del framework, pero se deben verificar todas las restricciones del lock, extensiones, INI, usuario y socket reales de FPM. No resolver de nuevo dependencias ni ignorar requisitos de plataforma. |
| Paquetes y middleware | Probar Sanctum 4, autenticación propia, alias `permiso`, CSRF, errores y proveedores con dependencias de producción sin require-dev. Confirmar que ninguna clase de desarrollo sea necesaria al arrancar. `package:discover` ejecuta la aplicación y escribe archivos. |
| Dependencia de internals | Coordinador usa Reflection sobre `Connection::reconnector`, `hasDirectConnection()`, PDO y detección de pérdidas de conexión. Un cambio de framework puede afectar bloqueo, reconexión o descarte. Exigir ensayo real en 10.11.14 con huella actual, incluyendo cobros ordinarios: gate cerrado no elimina estos riesgos. |
| Fechas y Carbon 3 | Carbon cambia entre mayores. `FechaLocal`, filtros y servicios de caja convierten UTC/Bolivia; `config/app.php` fija America/La_Paz. Comparar límites de medianoche, fecha inclusiva/exclusiva, orden, promociones, numeración y duración/signo de diferencias; no cambiar fechas históricas. |
| Defaults heredados | Cache lee `CACHE_DRIVER`, sesión usa file por defecto y conexión mysql usa `DATABASE_URL`. No copiar variables de un esqueleto moderno suponiendo que se leerán. Resolver configuración efectiva, incluyendo caché compilada y variables del servicio, sin publicar secretos. |
| Memoria y disco | OOM puede matar FPM/MariaDB y dejar un commit incierto. 3,3 GB no garantizan espacio para vendor, dos releases, respaldos, restauración, temporales y logs. Medir máximos y reservar margen; construir artefactos fuera de la instancia. No instalar Node/compilar ni ejecutar Composer update en producción. Swap o ampliación requieren decisión de infraestructura separada. |
| Dos aplicaciones | Un candidato con cookies, cache, colas o credenciales compartidas podría modificar producción. Host privado distinto, almacenamiento/cookies/prefijos separados, base restaurada y cuenta limitada a ella; sin correo, cron, workers o servicios externos productivos. El aislamiento por carpeta no basta. |
| Sesiones y cifrado | Sesión file en `storage/framework/sessions`; cookie depende de APP_NAME salvo SESSION_COOKIE. Conservar APP_KEY productiva de forma segura; nunca key:generate. Verificar cookie/dominio/secure/same-site, proxy HTTPS, sesiones antiguas y retorno a L10. Si la compatibilidad falla, acordar reautenticación; no copiar sesiones de prueba a producción. |
| Permisos | Usuario FPM, travesía de `/home/ubuntu`, ACL, socket y enlaces pueden producir 403/500. Exponer solo `public/`, negar dotfiles y .env. Escritura mínima en storage y bootstrap/cache; nunca chmod 777 ni chown recursivo de toda la aplicación. |
| Archivos y assets | `config/filesystems.php` usa storage/app y app/public, con errores de escritura no lanzados por defecto. Inventariar también archivos dentro de public y rutas personalizadas. Probar carga/lectura/eliminación autorizada en prueba, URLs y assets; no compartir vistas/cache compiladas entre releases. Preservar uploads nuevos al volver a L10. |
| Datos financieros | Hay pasos float/round en pedidos, promociones, cobros y reportes. No se ha demostrado aquí error de centavo, pero SQLite no certifica DECIMAL MariaDB. Validar límites, redondeo, descuentos y sumas con un oráculo decimal exacto. `monto_recibido` incluye cambio: no es ingreso. Pagos mixtos cuentan en ambos métodos; sus conteos no son ventas únicas. |
| Trabajos y efectos externos | Kernel local no define tareas activas, pero cron/Supervisor/systemd AWS son desconocidos. Inventariar productores/consumidores y payloads serializados. Frenar ambos antes del corte; impedir workers L10/L13 simultáneos y duplicación de correos/cobros. |

### Migraciones y MariaDB

La publicación preferida es **solo código y dependencias**, sin DDL/DML de conversión, únicamente si el esquema productivo exacto ya satisface al candidato y permite volver a L10. Si no, NO-GO y plan de esquema independiente; este documento no propone ejecutar migraciones ni seeders.

Riesgos observados en archivos locales:

- `2026_10_11_000001_actualizar_operacion_cafeteria.php` combina DDL con actualización de partes QR/efectivo y renumeración de pedidos leyendo todos con `get()`: posible consumo elevado y efectos financieros si se repite fuera de contexto.
- Migraciones del 06/10 agregan unicidad de stock y venta/pedido, backfill de subtotal y ALTER de proveedor. Duplicados, NULL, tipos, índices o diferencias de driver pueden impedirlas o alterar resultados.
- `2026_10_26_000001_preparar_cierres_diarios_versionados.php` agrega fecha operativa nullable única, versiones/instantáneas, DECIMAL, columna generada STORED y cambia FK del responsable a RESTRICT. Su down rechaza datos de cierre existentes y puede eliminar estructuras; no constituye recuperación segura.
- MariaDB puede confirmar implícitamente al ejecutar DDL: envolver la migración en una transacción no garantiza revertirla. Un fallo puede dejar esquema parcialmente cambiado sin registro de migración.
- Comparar DDL completo de ambas bases: motores InnoDB, columnas/escala/defaults/NULL, columna generada, índices, FK y acciones, charset/collation, sql_mode, aislamiento, zona horaria y permisos efectivos. Las 26 filas del registro no prueban todo esto ni integridad de datos.
- Mantener inicialmente el driver mysql configurado. Cambiar a un driver mariadb modifica ramas de migraciones que comparan literalmente `mysql`; requiere ensayo separado. No desactivar FK ni marcar migraciones como ejecutadas para encubrir diferencias.

## 3. Reglas para comandos propuestos

**Ningún comando de esta sección fue ejecutado.** Linux/Bash para AWS; ejecutar individualmente, revisar salida y detenerse ante error. Los marcadores `<...>` deben reemplazarse por valores verificados en el inventario; no son comandos listos para pegar. No usar contraseñas en argumentos, historial o evidencias.

| Clase | Significado |
| --- | --- |
| R — solo lectura | Inspección sin cambio intencional de aplicación/datos; puede generar logs del sistema. SQL únicamente con conexión de lectura confirmada y bajo impacto. |
| T — cambios en prueba | Escribe solo en candidato y recursos desechables previamente aislados. Arrancar Laravel o hacer HTTP puede escribir logs/sesiones; no clasificarlo como R. |
| P — cambios productivos | Backups, mantenimiento, caches, permisos, configuración de publicación, recargas y tráfico real. Requieren ventana y autorización posterior. |

### R — inventario futuro

```bash
git -C /var/www/cafeteria rev-parse HEAD
git -C /home/ubuntu/karma_laravel13_prueba rev-parse HEAD
sha256sum /home/ubuntu/karma_laravel13_prueba/composer.lock
php -v
php --ini
php -m
php-fpm8.3 -v
free -m
df -h
df -i
du -sh /var/www/cafeteria /home/ubuntu/karma_laravel13_prueba
systemctl is-active nginx php8.3-fpm mariadb
readlink -f /var/www/cafeteria/public
namei -l /home/ubuntu/karma_laravel13_prueba/public/index.php
stat /var/www/cafeteria/storage /var/www/cafeteria/bootstrap/cache
sha256sum <RUTA_RESPALDO>/cafeteria_pre_laravel13_20261026.sql
```

Si las carpetas AWS no tienen Git, verificar manifiesto del artefacto firmado/hasheado; no inicializar repositorios para aparentar trazabilidad. Inspeccionar localmente Nginx/FPM, cron y servicios sin copiar configuraciones con secretos a tickets; no usar phpinfo público. Verificar identidad de FPM además de CLI.

SQL R propuesto, **no ejecutado**, una conexión de lectura explícita por base, sin USE implícito ni credenciales embebidas:

```sql
SELECT DATABASE(), VERSION(), @@sql_mode, @@tx_isolation, @@session.time_zone;
SELECT migration, batch FROM migrations ORDER BY migration;
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME;
SHOW CREATE TABLE ventas;
SHOW CREATE TABLE cierre_cajas;
SHOW CREATE TABLE cierre_caja_versiones;
SHOW CREATE TABLE cierre_caja_version_ventas;
SELECT COUNT(*) AS ventas, SUM(total) AS total,
       SUM(monto_efectivo) AS efectivo, SUM(monto_qr) AS qr FROM ventas;
SELECT pedido_id, COUNT(*) AS cantidad FROM ventas
GROUP BY pedido_id HAVING COUNT(*) > 1;
SELECT COUNT(*) AS partes_inconsistentes FROM ventas
WHERE total <> monto_efectivo + monto_qr;
```

Confirmar columnas antes de consultar; ausencia es hallazgo NO-GO, no motivo para alterar esquema. Extender comparación a usuarios, roles/pivotes, pedidos/detalles, numeradores, stocks/movimientos, bitácoras y todas las FK. Comparar huérfanos y agregados por día/método con evidencia saneada; no publicar filas personales. Conteos/sumas iguales no prueban identidad de filas. Para una línea base coherente, detener escritores o usar snapshot consistente bajo supervisión; no comparar lecturas separadas mientras hay ventas.

### T — preparación del candidato y ensayos

Solo después de comprobar aislamiento de base, credenciales, sesiones, servicios externos y configuración compilada. No reutilizar el arnés destructivo de integración sobre la restaurada: su README exige base dedicada marcada `cafeteria_caja_integracion` y usuario `karma_caja_it`.

```bash
cd /home/ubuntu/karma_laravel13_prueba
composer validate --strict --no-check-publish
composer check-platform-reqs --no-dev
composer audit --locked --no-dev
php artisan --version
php artisan route:list
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Composer puede usar red/cache y Artisan puede arrancar proveedores/escribir: se clasifican conservadoramente T. Preparar vendor y assets reproducibles fuera de AWS desde los locks, probar instalación sin require-dev; scripts/plugins deben revisarse. No usar `composer update`, `--ignore-platform-reqs` ni copiar vendor de Windows a Linux. Ensayar exactamente los caches que se usarían en producción; no transportar config.php compilado con la base de pruebas al release productivo. Un fallo de route:cache exige decisión documentada antes del corte, no improvisación.

Las suites y el arnés se proponen como ensayos futuros siguiendo sus guardas y comandos documentados, no se ejecutan en esta tarea. El gate de aplicación continúa false; cualquier adaptador de test se limita al arnés aislado existente y no se publica.

### P — ejemplos de operaciones de ventana

No ejecutar hasta resolver los GO previos y completar rutas/servicios reales. Se conserva intacta `/var/www/cafeteria` como release de retorno; no hacer git pull, copiar encima ni reemplazar su vendor.

```bash
# En el release L10 actualmente servido, antes de cortar escritores externos:
cd /var/www/cafeteria
php artisan down --retry=60

# Respaldo fresco, con directorio seguro existente y capacidad comprobada.
# Cuenta/archivo de opciones ya autorizado; revisar motores y rutinas antes.
umask 077
mariadb-dump --defaults-extra-file=<ARCHIVO_SEGURO_EXISTENTE> --single-transaction --quick --routines --triggers --events cafeteria_pro > <RESPALDO_NUEVO_NO_EXISTENTE>.sql
sha256sum <RESPALDO_NUEVO_NO_EXISTENTE>.sql

# Solo en release productivo L13 preparado, con configuración productiva validada:
cd <RELEASE_L13_PRODUCTIVO>
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Tras instalar la configuración de Nginx previamente ensayada:
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm
```

Verificar código de salida y tamaño del dump; no basta hash de archivo vacío o truncado. `--single-transaction` requiere tablas transaccionales y ausencia de DDL concurrente. Respaldar además archivos persistentes y configuración con sus permisos y secretos bajo acceso restringido. El respaldo del 26/10 no sustituye al de la ventana del 27/10 o posterior. No sobrescribir respaldos existentes.

El cambio de root/enlace Nginx, pausa/reanudación de workers, copia de uploads y ACL se describe en fases, sin comandos genéricos peligrosos: faltan ruta de vhost, usuario FPM, montajes y unidades reales. Esos pasos exactos y su inversa deben quedar completados en el registro operativo **antes** de autorizar. No asumir que existe un symlink `current`. Servir directamente desde `/home/ubuntu` queda condicionado a permisos y decisión de publicación; se prefiere un release productivo preparado y verificado fuera de esa carpeta de prueba.

No usar `optimize:clear` o `cache:clear` indiscriminados: pueden afectar cache compartida. `artisan down` de un release no bloquea otro con storage distinto, ni cron, workers o solicitudes ya iniciadas. Debe mantenerse una barrera de mantenimiento en el punto de entrada durante el cambio.

## 4. Despliegue por fases y puertas GO/NO-GO

Responsables a designar: operador de despliegue, responsable de BD/recuperación, validador funcional/financiero y quien autoriza la ventana. Registrar hora Bolivia y UTC, huellas, resultados, responsable y decisión por puerta. Ante un pendiente obligatorio, NO-GO.

| Fase | Acciones y evidencia exigida | GO / NO-GO |
| --- | --- | --- |
| 0. Identidad e inventario (R) | Igualar commit/lock/vendor/assets del candidato con artefacto aprobado; inventariar root real, FPM, servicios, bases, almacenamiento y variables efectivas saneadas. Revisar tres guías mayores y vulnerabilidades del lock. | GO con trazabilidad completa y sin incompatibilidad/brecha bloqueante. AWS y configuración efectiva pendientes. |
| 1. Compatibilidad de datos (R/T) | Comparar producción/restaurada y esquema requerido. Ejecutar candidato y L10 conservado contra copias con el esquema y datos esperados, nunca contra producción. Probar que ambos leen/escriben sin perder partes de pago, stock, numeración y restricciones. | GO solo si publicación sin migraciones y retorno conservando datos están demostrados. Si producción difiere, NO-GO; plan de esquema separado. |
| 2. Ensayo operativo aislado (T) | Suite de huella actual; integración MariaDB 10.11.14; HTTP por Nginx/FPM con dependencias sin dev y caches previstos. Login/logout/expiración, sesión previa y retorno L10, permisos negativos, CSRF real, pedidos, stock, promociones, efectivo/QR/mixto/cambio, doble envío y reportes. Probar archivos/assets y gate cerrado con rechazo de confirmación/rectificación. | GO sin duplicación, diferencias monetarias, fugas, fallos de acceso o regresiones. La omisión PHPUnit debe explicarse y cubrirse con evidencia real pertinente. |
| 3. Capacidad y recuperación (T/R) | Ensayar concurrencia y carga representativa, memoria FPM+MariaDB, locks, espacio máximo, respaldo/restauración completa y vuelta al release L10. Cronometrar detección, corte, recuperación y validación. | GO con capacidad y margen medidos, RTO/RPO aprobados y retorno ensayado. No estimar viabilidad solo con 909 MiB/3,3 GB. |
| 4. Preparación de ventana (P) | Aprobar responsable/horario/comunicación; congelar cambios. Preparar release independiente, configuración protegida, archivos persistentes y caches propios. Definir barrera Nginx, rutas exactas y operación inversa; validar nginx -t. Definir umbrales de observación con base del ensayo. | GO documental y operativo firmado. Prohibido iniciar corte sin procedimiento concreto y copia L10 verificable. |
| 5. Congelar y respaldar (P) | Activar mantenimiento de entrada, pausar productores/consumidores en ambos releases y esperar solicitudes/transacciones en curso. No forzar terminaciones financieras sin reconciliar. Registrar línea base consistente, respaldo fresco DB+archivos+config y manifiesto fuera del único punto de fallo. | GO con escritores detenidos y recuperación disponible. Si backup/capacidad/consistencia falla, abortar antes del cambio y volver a L10 controladamente. |
| 6. Publicación contenida (P) | Con barrera activa, apuntar exclusivamente al public del release aprobado, verificar root y caches con BD productiva correcta. Recargar Nginx/FPM controladamente; no reiniciar MariaDB. Probar HTTP restringido y lecturas autorizadas sin reabrir tráfico general. | GO si versión real FPM, rutas, logs, permisos, archivos y agregados coinciden; gate false. Cualquier divergencia financiera, 502/500 persistente u OOM: recuperar. |
| 7. Apertura vigilada (P) | Retirar mantenimiento del release servido y barrera de entrada de forma ordenada. Reanudar consumidores compatibles una sola vez. Validación de escrituras solo con operaciones comerciales legítimas y supervisadas; no crear ventas ficticias productivas. Observar errores, latencia, RAM, disco, locks y reconciliación de ventas. | GO de continuidad tras ventana representativa que incluya pico y cambio de día. Si hay cobro incierto, detener y reconciliar sin reintento. |
| 8. Cierre de ventana (R/P) | Responsable funcional acepta evidencia; conservar L10, backups, logs y artefactos durante retención acordada. Documentar incidentes y capacidad. | Publicación aceptada solo tras pruebas reales. Gate sigue false; habilitar cierres exige expediente distinto. |

Pendiente fijar RTO máximo, RPO y duración/umbrales de observación basados en ensayo. Como disparadores inmediatos: un duplicado o descuadre financiero nuevo, destino de BD incorrecto, exposición de secretos, corrupción/ausencia de archivos, OOM o pérdida de acceso general. No esperar al final de la ventana para detener escrituras.

## 5. Recuperación a Laravel 10

### A. Fallo antes de admitir escrituras L13

1. Mantener barrera de entrada y consumidores pausados; conservar logs saneados, huellas y hora del fallo. Verificar que no hubo escrituras de aplicación, incluso de workers/sesiones/cron.
2. Revertir únicamente el root/enlace/configuración de publicación al `/var/www/cafeteria/public` anterior mediante la inversa ensayada. Restaurar configuración de servicio anterior si fue cambiada; validar Nginx y recargar Nginx/FPM para retirar código OPcache antiguo.
3. Conservar vendor, APP_KEY y configuración L10 anteriores. No sustituirlos con los de L13 ni reutilizar caches compilados L13. Mantener archivos persistentes correctos y aplicar política de sesiones ensayada.
4. Validar versión servida por FPM, login, permisos, archivos, pedidos/ventas y agregados contra línea base. Solo después, en el release L10 servido, `php artisan up` **[P]**, retirar barrera y reanudar consumidores una sola vez.
5. Si los datos no cambiaron y el esquema es compatible, no restaurar BD: introduciría riesgo innecesario. La duración medida debe cumplir RTO.

### B. Fallo después de admitir escrituras o con resultado incierto

1. Volver a mantenimiento de entrada y detener nuevos escritores de ambos releases. Una respuesta 500/timeout no demuestra rollback. No repetir POST, cobros, confirmaciones ni rectificaciones.
2. Esperar o investigar operaciones en curso con el responsable BD. Preservar evidencia y tomar respaldo del estado posterior al incidente, incluidos uploads nuevos y auditoría. Registrar intervalo e IDs afectados sin secretos.
3. Si L10 fue probado con ese esquema y formato de escrituras, recuperar **solo código**, conservar la BD actual y reconciliar todas las operaciones del intervalo antes de abrir. No ejecutar down de migraciones ni borrar versiones/eventos para que arranque.
4. Si L10 no es compatible, NO abrirlo contra esa BD. Mantener servicio detenido; evaluar reparación hacia adelante o recuperar a una base nueva desde respaldo fresco y reproducir cambios confirmados mediante un procedimiento específico aprobado. No hay garantía de RPO cero sin binlogs/registro suficiente y ensayo de reproducción: disponibilidad, retención y coordenadas de binlog están pendientes.
5. Restaurar un respaldo sobre `cafeteria_pro` perdería ventas, pedidos, stocks, archivos y auditoría posteriores. No proponer importación destructiva como rollback automático. Toda pérdida debe cuantificarse y aprobarse por responsables del negocio/BD. Nunca fusionar filas financieras manualmente sin reconciliación trazable.
6. Verificar FK, conteos y sumas decimales por día/método, venta única por pedido, numeradores, reservas/stock/movimientos y coherencia de auditoría. Respetar snapshots/versiones oficiales; no reconstruir cierres a partir de precios actuales.
7. Con evidencias coherentes y autorización operativa, seguir las verificaciones de reapertura del caso A. Si no se prueba resultado de un commit, mantenerlo como incierto y escalar.

La recuperación de infraestructura (instancia/disco) también necesita copia fuera de la instancia, acceso seguro y ensayo de restauración. Un SHA-256 válido demuestra integridad del archivo respecto del hash, no integridad lógica, exhaustividad ni tiempo de recuperación.

## 6. Validaciones pendientes para publicar con cierres bloqueados

- [ ] Huella exacta AWS y comparación L10/L13; matriz completa de las tres guías mayores y revisión de paquetes/vulnerabilidades.
- [ ] PHP/FPM, extensiones, permisos, TLS/proxy, root Nginx y config efectiva verificados sin revelar secretos.
- [ ] Esquema productivo real comparado; ausencia de migraciones necesarias demostrada. Compatibilidad inversa L10 con datos escritos por L13 ensayada.
- [ ] 51 escenarios históricos repetidos con huella actual y MariaDB 10.11.14; cubrir nueva revalidación de cobro, revocación durante GET_LOCK real e interacción con inventario/edición. No basta heredar 51 PASS de 10.4.32.
- [ ] Ensayo HTTP Nginx/FPM completo con sesiones persistentes, CSRF, permisos, assets, archivos, dependencias sin dev y caches previstos.
- [ ] Validación decimal y temporal de ventas/reportes; conciliación histórica y pagos mixtos sin diferencias no explicadas.
- [ ] Capacidad de RAM/disco y carga realista; presupuesto de FPM/BD y margen aprobados; monitoreo y umbrales definidos.
- [ ] Inventario y pausa/reanudación ensayada de cron/colas/servicios externos; aislamiento efectivo de prueba.
- [ ] Backup fresco restaurable DB+archivos+config; copia externa; retorno L10 cronometrado; RTO/RPO y retención acordados.
- [ ] Procedimiento exacto de corte/inversa, responsables y ventana aprobados; gate false validado en artefacto y comportamiento HTTP.

Hasta cerrar estas condiciones, **no se autoriza el despliegue**. Resolverlas permitiría evaluar una publicación con cierres bloqueados; no concede autorización automática.

## 7. Requisitos adicionales para cierres financieros definitivos

**Decisión independiente: NO-GO.** No cambiar `permiteCierreDefinitivo()`, bindings o configuración para habilitarlos. Tener permisos de caja o publicar Laravel 13 no levanta el gate.

1. Ejecutar en arnés desechable las pruebas reales cobro/cierre/rectificación simultáneos, unicidad de fecha y versión, espera/timeout/liberación de GET_LOCK, revocación real en User lock y orden de bloqueos con roles/inventario. Exigir una conexión primaria sin separación lectura/escritura, no persistente y sin reconexión automática durante coordinación.
2. Probar pérdidas reales de conexión en fases identificables de COMMIT con proxy dedicado: antes del envío, después del envío sin respuesta y después de persistencia observada. No detener MariaDB compartido. Demostrar ausencia de repetición financiera y observar mutex desde sesión independiente.
3. Certificar reconciliación real con @@tx_isolation y primario 10.11.14: evento confirmado único, actor, versión objetivo/vigente, instantáneas, importes, UUID de rectificación y estados inciertos. La ausencia observada de fila no prueba rollback ni autoriza reintentar.
4. Resolver formalmente el límite de confirmación inicial sin UUID persistido de intento ni incidente estructurado equivalente a rectificación. Fecha/V1/actor/importes permiten observar resultado pero no atribuir inequívocamente dos solicitudes concurrentes. Definir criterio de aceptación y procedimiento humano; si se exige idempotencia distribuida, requiere trabajo de código separado. Esta entrega no lo implementa.
5. Aprobar política de redondeo decimal exacto y casos de 0,01, 0,10/0,20, medio centavo, descuentos/topes, límites DECIMAL, múltiples líneas y períodos extensos. Verificar total=efectivo+QR y diferencia exacta; los reportes con float no sirven de único oráculo.
6. Validar día cerrado y medianoche UTC/Bolivia, histórico con fecha operativa NULL, conservación inmutable de snapshots, secuencia de versiones, permisos negativos y revocación tras espera. No recalcular históricos con precios/promociones actuales.
7. Verificar catálogo y asignaciones por identidad, sin excepciones por nombre de rol: consultar_caja; además confirmar_cierre_caja o rectificar_cierre_caja según operación; reconciliación exige consultar_caja + consultar_bitacora. Cargas/asignaciones quedan fuera de esta tarea; no ejecutar seeders ni sincronizaciones masivas.
8. Ensayar durabilidad/recuperación, disponibilidad de auditoría/logs y tratamiento de fallos simultáneos, capacitación para no reenviar POST y revisión de incidente por supervisor. No presentar simulaciones SQLite como certificación de red/durabilidad.
9. Obtener aceptación funcional/financiera y autorización separada documentada. Cualquier futura habilitación exige nueva tarea, revisión y pruebas; no es paso final implícito de este plan.

## 8. Fuentes oficiales consultadas

Consultadas el 27/10/2026; aplicar al lock y código concretos, no asumir que todo cambio listado afecta a KARMA.

- [Laravel 11: guía de actualización](https://laravel.com/docs/11.x/upgrade): cambios acumulativos desde 10, paquetes, autenticación y modificaciones de columnas.
- [Laravel 12: guía de actualización](https://laravel.com/docs/12.x/upgrade): revisión de compatibilidad y transición a Carbon 3.
- [Laravel 13: guía de actualización](https://laravel.com/docs/13.x/upgrade): requisitos y cambios acumulativos desde 12.
- [Laravel: despliegue](https://laravel.com/docs/deployment): PHP mínimo, directorio público, permisos y caches. Verificar requisitos transitivos con el lock además del mínimo del framework.
- [MariaDB: sentencias que causan commit implícito](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit): fundamento de no usar una transacción o migrate:rollback como recuperación universal de DDL.

## 9. Registro de esta entrega

Archivo creado: `docs/PLAN_DESPLIEGUE_LARAVEL13_KARMA.md`. Ningún archivo de aplicación, migración o configuración modificado. Sin comandos remotos/BD, migraciones, seeders, commits o push. Estado final de la recomendación: **despliegue pendiente de validaciones operativas; cierres definitivos deshabilitados**.
