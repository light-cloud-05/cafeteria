# Cierre de caja para defensa académica local

**DEMOSTRACIÓN ACADÉMICA — DATOS FICTICIOS**

Implementación local. No habilita el coordinador normal, no usa AWS y no requiere datos reales. La excepción académica no certifica operaciones financieras productivas.

## Destino y condiciones obligatorias

- Base exacta `cafeteria_defensa_demo`; cuenta SQL nueva `karma_defensa_demo`, distinta del usuario de la interfaz `defensa.demo`.
- Entorno exacto `demostracion` y `CAJA_DEMO_ACTIVA=true`. Cualquier combinación parcial rechaza el arranque, incluso activar la bandera en producción.
- TCP a `127.0.0.1`, puerto explícito, sin socket, URL alternativa, opciones PDO personalizadas (incluido INIT_COMMAND), conexiones persistentes ni read/write splitting. PDO debe informar `127.0.0.1 via TCP/IP`.
- Se comprueban DATABASE(), CURRENT_USER(), USER(), CURRENT_ROLE(), @@hostname, @@port y SHOW GRANTS en la sesión efectiva. El hostname esperado se fija expresamente. Un túnel TCP no demuestra por sí solo que un servidor es local: usar una instancia local bajo control del operador, sin túneles/proxies.
- Cuenta autenticada y usuario efectivo de conexión: `karma_defensa_demo@localhost` o `karma_defensa_demo@127.0.0.1`. Sin cuentas anónimas, root, host `%`, roles asignados (aunque inactivos), PROXY, GRANT OPTION ni privilegios globales excepto USAGE.
- Solo SELECT, INSERT, UPDATE y DELETE sobre la base **literal**, con guiones bajos escapados en el GRANT. Se rechazan comodines, ALL PRIVILEGES, privilegios DDL, otros esquemas y grants de tabla/columna no reconocidos. El acceso inherente de lectura a metadatos information_schema no equivale a acceso a datos de otras aplicaciones.
- Antes de cada sentencia Laravel sobre la conexión demo se repiten las comprobaciones. Se rechaza el cambio de PDO, reconexión y SQL de cambio de destino/privilegios. El adaptador también verifica antes y después de adquirir el mutex real del coordinador.

No editar `.env` ni reutilizar la cuenta, contraseñas, sesiones, caché o base de la aplicación normal. Usar preferentemente una copia local separada del proyecto y una instancia MariaDB local dedicada. No publicar el servidor de demo en la red.

## Preparar infraestructura (operador; no ejecutado en esta entrega)

Se necesita PHP 8.3 compatible con el lock, extensiones PDO MySQL, un MariaDB local y el esquema de las migraciones actuales **sin datos**. No copiar un dump con datos reales ni ejecutar DatabaseSeeder.

Un administrador local debe crear una base nueva y una cuenta nueva. Si cualquiera existe, detenerse y revisar su origen: no modificar una cuenta existente, no usar CREATE USER IF NOT EXISTS, ALTER USER ni REVOKE. Ejemplo de SQL para revisión del operador, no para ejecutar con la aplicación:

```sql
CREATE DATABASE `cafeteria_defensa_demo` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'karma_defensa_demo'@'127.0.0.1' IDENTIFIED BY '<CONTRASEÑA_NUEVA_ALEATORIA>';
GRANT SELECT, INSERT, UPDATE, DELETE ON `cafeteria\_defensa\_demo`.* TO 'karma_defensa_demo'@'127.0.0.1';
```

El esquema debe instalarse por separado con una cuenta de instalación nueva y limitada a esa base, en la instancia local aislada. La cuenta de ejecución no tiene permisos para migrar. No se proporciona un comando genérico `artisan migrate` contra la configuración actual: requiere un procedimiento de instalación aparte, con comprobación del destino antes de cualquier DDL. **El preparador no instala ni repara el esquema.** No se ejecutaron migraciones MariaDB en esta entrega.

El esquema debe contener únicamente tablas InnoDB generadas por las migraciones del proyecto, sin vistas, triggers, rutinas ni eventos añadidos. El operador que instala el esquema debe verificarlo con su cuenta de instalación: la cuenta DML puede no tener visibilidad de triggers. Nunca concederle permisos globales para facilitar la demo. No debe haber procesos externos escribiendo en esta base.

Copiar `.env.demostracion.example` a `.env.demostracion` en la copia local. Completar una APP_KEY nueva exclusiva de esa copia, la contraseña de la cuenta nueva, el puerto y CAJA_DEMO_SERVIDOR con el @@hostname real. El archivo de credenciales está ignorado por Git. No reutilizar caches compilados de producción; arrancar la copia demo sin bootstrap/cache/config.php preexistente. No ejecutar key:generate sobre el `.env` normal.

## Preparador de datos (NO ejecutado en esta entrega)

**Destino anunciado antes de escribir: `cafeteria_defensa_demo`, conexión y cuenta verificadas.**

```powershell
C:/php83/php.exe artisan caja:preparar-demo --env=demostracion --acepto-base=cafeteria_defensa_demo
```

Ejecutar únicamente desde la copia local configurada y después de preparar el esquema. El comando imprime destino y protecciones antes de insertar, verifica el aislamiento y adquiere el mutex compartido con ventas/cierres. Exige todas las tablas vacías excepto `migrations`, comprueba tablas requeridas e InnoDB, y carga todo en una sola transacción sin reintento. Rechaza datos existentes; nunca borra tablas, contraseñas, roles o permisos para reiniciar. No reejecutarlo para recuperar una contraseña perdida: preparar otra instancia ficticia por un procedimiento separado.

Crea un usuario `defensa.demo`, una contraseña aleatoria nueva mostrada una sola vez, un rol nuevo y asignaciones nuevas. Guardar esa contraseña fuera del repositorio, capturas y logs compartidos. Solo asigna permisos para el guion, sin alterar ningún registro previo.

Los fixtures son de **ayer en Bolivia**, a mediodía: efectivo Bs 30, QR Bs 20 y mixto Bs 50 (efectivo Bs 20 + QR Bs 30). Totales: vendido Bs 100, efectivo Bs 50, QR Bs 50. Incluyen pedidos pagados y sus detalles; no precargan cierres. El stock inicial de Bs/productos no pretende reconstruir movimientos históricos: es saldo ficticio de partida. La bitácora identifica la carga como PREPARACION_DEMO; no se inventan eventos de cobro HTTP para esos fixtures.

Arranque local desde la copia dedicada:

```powershell
C:/php83/php.exe artisan serve --env=demostracion --host=127.0.0.1 --port=8013
```

Entrar en http://127.0.0.1:8013 con la cuenta recién creada. Debe aparecer el aviso permanente de datos ficticios. Si falla una guarda, no cambiarla para continuar: revisar el destino y los grants con el operador. No activar APP_DEBUG para exponer credenciales.

## Guion de defensa y resultados esperados

1. **Venta en vivo:** registrar un pedido ficticio del producto de Bs 10 y cobrarlo en Efectivo/QR/Mixto desde la interfaz. Mantener CSRF, hash de versión del pedido y permisos. Consultar la previsualización de hoy: incluye esa venta. No se puede cerrar hoy hasta que termine el día.
2. **Vista previa del día anterior:** Caja → Previsualización → fecha indicada por el preparador. Debe mostrar total Bs 100, efectivo Bs 50 y QR Bs 50. La venta en vivo de hoy no se mezcla con ese día.
3. **Validación de diferencia:** Formulario de confirmación → ayer → contado `48.00`, observación vacía. Debe rechazar por falta de explicación sin registrar cierre.
4. **Confirmación:** repetir con `48.00` y “Faltante ficticio de Bs 2 para defensa”. Debe redirigir al detalle guardado. Esperado: versión 1, diferencia `-2.00`, responsable de sesión, tres ventas e instantáneas y observación.
5. **Historial:** abrir listado, detalle e historial de versiones; consultar versión 1 y bitácora (`CIERRE_CAJA`). Un cierre inicial tiene una sola versión: historial no implica múltiples rectificaciones.
6. **Duplicado:** volver al formulario y confirmar la misma fecha. Debe rechazar, mantener un cierre y un evento CIERRE_CAJA para esa fecha.
7. **Opcional, rectificación:** con permiso específico, contado `50.00`, observación y motivo explícitos. Debe crear versión 2, diferencia cero, conservar versión 1 e insertar RECTIFICACION_CAJA. No borra la diferencia anterior.
8. **Permisos y CSRF:** ensayar en pruebas automatizadas: sin consultar/confirmar → 403; POST con token ausente/inválido → 419. No revocar ni alterar permisos de cuentas existentes para demostrarlo.

Para una secuencia sobre exactamente la misma venta: registrar el día anterior y cerrar al día siguiente. No se cambia el reloj ni se permite cerrar el día abierto. Repetir con nuevas fechas exige un conjunto de datos ficticios nuevo; el preparador deliberadamente no destruye ni reinicia datos existentes.

## Pruebas y alcance

```powershell
C:/php83/php.exe vendor/bin/phpunit --filter 'AislamientoCajaDemoTest|CajaDemoConexionTest|CajaFormulariosTest|CierreDiarioServiceTest|RectificacionCierreDiarioTest|AutorizacionCajaTest'
```

Las pruebas Unit validan rechazos de configuración, identidad y grants con datos sintéticos y mantienen false el coordinador normal. Feature usa SQLite exclusivamente `:memory:` y los dobles secuenciales existentes: comprueba formulario, CSRF real, permisos, diferencias, instantáneas, auditoría, historial y duplicados. Crea el esquema dentro de memoria; no ejecuta migraciones sobre MariaDB ni utiliza cafeteria_pro. No es una certificación de MariaDB ni de transporte real.

Queda por ensayar con el operador: cuenta/grants reales, formato PDO/SHOW GRANTS de su servidor, instalación del esquema vacío, ejecución del preparador y recorrido en navegador. No se ejecuta automáticamente ningún preparador MariaDB. La sintaxis de grants es deliberadamente conservadora: un formato no reconocido bloquea la demo.

Explicar a la ingeniera: son registros persistidos en una base ficticia, con las mismas validaciones y auditoría; no hay integración bancaria ni dinero real. Se demuestra el flujo académico, no tolerancia a fallos de red, capacidad, concurrencia productiva ni contabilidad de gastos/retiros/fondo inicial. El hostname es una comprobación operativa, no una autenticación criptográfica del servidor.

## Verificación de esta entrega

- Suite completa `C:/php83/php.exe vendor/bin/phpunit`: **243 pruebas, 3.380 aserciones, cero fallos, una omitida**, PHP 8.3.36. La omitida exige concurrencia real con dos conexiones MariaDB; no se presenta como aprobada.
- Sintaxis PHP comprobada en los diez archivos PHP nuevos/modificados de esta tarea; `git diff --check` sin errores de whitespace.
- PHPUnit necesitó ejecutarse fuera del sandbox porque `is_readable` devolvía false aun pudiendo incluir el archivo. Se regeneró previamente el autoloader ignorado de vendor con Composer `dump-autoload --no-scripts --no-plugins`; no se instalaron/actualizaron paquetes ni se cambiaron locks.
- No se ejecutó el preparador contra MariaDB, no se creó infraestructura ni se modificó cafeteria_pro. La prueba del comando sin aceptación se realiza solo dentro del proceso PHPUnit y termina antes de escribir.
- Sin migraciones MariaDB, AWS, commits ni push. Las pruebas Feature construyen únicamente el esquema SQLite en memoria.

Archivos de esta tarea: `.gitignore`, `.env.demostracion.example`, `config/demostracion.php`,
`app/Services/Caja/AislamientoCajaDemo.php`, `app/Services/Caja/CoordinacionCajaDemostracion.php`,
`app/Services/Caja/CoordinacionCobrosCaja.php` (solo comentario del contrato),
`app/Providers/AppServiceProvider.php`, `app/Support/DisponibilidadCajaWeb.php`,
`app/Console/Commands/PrepararCajaDemostracion.php`, `resources/views/auth/login.blade.php`,
`resources/views/layouts/app.blade.php`, `resources/views/caja/_aviso.blade.php`,
`tests/Unit/AislamientoCajaDemoTest.php`, `tests/Feature/CajaDemoConexionTest.php`,
`tests/Feature/CajaFormulariosTest.php` y este documento.

Se conservaron los cambios previos en los cinco archivos del arnés MariaDB/guard y el plan de despliegue sin seguimiento; no forman parte del diff de esta tarea.
