# Instalación exclusiva del esquema de demostración

Preparado para revisión. **No se ejecutó ninguna instalación, migración, seeder ni
fixture en esta tarea.** No se cambiaron credenciales, permisos o archivos .env.

## Destino cerrado

- PHP: C:/php83/php.exe.
- MariaDB 10.4.32, TCP 127.0.0.1:3308, sin túneles/proxies.
- Base única: cafeteria_defensa_demo.
- Instalador SQL: karma_demo_instalador@localhost o @127.0.0.1.
- Runtime SQL: karma_defensa_demo, conserva exclusivamente DML.
- Servidor: debe coincidir con CAJA_DEMO_SERVIDOR de .env.demostracion.

La consulta de lectura realizada con el runtime confirmó MariaDB 10.4.32 y **cero
tablas/vistas visibles**. La cuenta DML no acredita por sí sola ausencia de triggers,
rutinas y eventos. Esa certificación completa queda pendiente del primer comando
de comprobación con la cuenta instaladora, cuya contraseña no se solicita en chat.

## Por qué es un ejecutor separado

Artisan --env=demostracion debe seguir arrancando con la cuenta runtime limitada.
AislamientoCajaDemo rechaza correctamente una cuenta instaladora con DDL. No se
introdujo una excepción para migrar dentro del runtime ni se desactivó ninguna
guarda. Tampoco se crea un .env con credenciales instaladoras.

tools/Instalar-Demo.ps1 pide la contraseña oculta y la entrega únicamente por stdin
al proceso PHP, usando UTF-8. No la coloca en argumentos, variables de entorno,
archivos ni salidas. No ejecutar bajo un depurador que vuelque memoria de procesos.

tools/instalar-demo.php usa el autoloader de vendor, pero **no bootstrap/app.php,
Artisan, .env operativo o cachés de configuración**. Lee .env.demostracion para
verificar su destino y el hostname esperado. No imprime contraseñas, hashes,
APP_KEY, grants o trazas PDO. Los valores DB_* heredados no definen su conexión.

InstaladorEsquemaDemo reutiliza Capsule, Migrator y DatabaseMigrationRepository de
Laravel instalados en vendor. Solo registra una conexión al PDO previamente
verificado de la cuenta instaladora. Aplica todas las migraciones locales en orden,
incluidas las históricas y las dos del CU26, y registra migrations. No incluye
seeders ni carga de datos. Las migraciones existentes no se modificaron.

## Comprobaciones previas a cualquier escritura

El modo -Comprobar es de solo lectura. Verifica:

1. Configuración demo exacta, host/puerto/base/usuario runtime esperados, entorno y
   activación expresa; sin URL/socket alternativos.
2. PDO MySQL TCP local no persistente, sin transacción abierta.
3. DATABASE(), CURRENT_USER(), USER(), @@hostname, @@port, CURRENT_ROLE(), versión,
   motor InnoDB, foreign_key_checks activo y modo SQL estricto sin sustitución de motor.
4. Grants efectivos de la cuenta instaladora. Solo USAGE global y privilegios sobre
   la base literal, sin roles, PROXY, GRANT OPTION u otros esquemas. Los guiones
   bajos del objeto deben aparecer escapados en SHOW GRANTS:
   `cafeteria\_defensa\_demo`.*. Un grant sin escapar se rechaza por contener comodines.
5. Cero tablas/vistas (incluida migrations), triggers, rutinas y eventos.

Privilegios requeridos, exclusivamente sobre esa base: SELECT, INSERT, UPDATE,
DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP; además SHOW VIEW, TRIGGER, EVENT y
ALTER ROUTINE para acreditar la inspección completa. ALL PRIVILEGES es admisible
solo sobre la base literal escapada y sin GRANT OPTION. No se admiten privilegios
globales de administración. El programa no concede ni revoca permisos.

Si faltan privilegios de inspección, detenerse y pedir al administrador que revise
**solo la cuenta instaladora**. No ampliar la cuenta karma_defensa_demo.

## Comandos exactos para revisar y ejecutar posteriormente

Desde una consola PowerShell local conforme a las políticas del equipo:

```powershell
Set-Location C:\xampp\htdocs\cafeteria_pro
& C:\php83\php.exe tools\instalar-demo.php --help
& .\tools\Instalar-Demo.ps1 -Comprobar
```

El segundo comando pide la contraseña instaladora de forma oculta y, si todo está
correcto, enumera las migraciones y termina con COMPROBACION DE SOLO LECTURA
COMPLETADA. No crea ni siquiera la tabla migrations o una marca de instalación.

**Comando de instalación para revisión; NO ejecutado todavía:**

```powershell
& .\tools\Instalar-Demo.ps1 -Instalar -AceptoBase cafeteria_defensa_demo
```

Antes de ejecutarlo, cerrar cualquier servidor Artisan u otro proceso que utilice
la demo. Revisar las migraciones enumeradas por -Comprobar. No abrir acceso de red
a la demo ni utilizar otras cuentas para escribir concurrentemente en ella.

El modo instalación vuelve a verificar todo después de adquirir el mismo mutex
de base que usa caja. Crea una marca exclusiva en
storage/app/instalacion-demo-3308-iniciada.json antes del primer DDL. Revalida
identidad/permisos y la sesión antes de cada sentencia Laravel y compara internamente
que no cambien los archivos de migración; las huellas nunca se imprimen.

No hay reconexión, reintento, rollback o limpieza automáticos. MariaDB confirma DDL
implícitamente: una instalación fallida puede dejar un esquema parcial. Tanto una
marca existente como cualquier objeto previo impiden reejecutar. **No borrar la
marca ni tablas para intentar de nuevo sin una revisión expresa del operador.**

Al finalizar verifica la lista completa de migraciones, tablas InnoDB sin vistas,
sin triggers/rutinas/eventos y tablas de aplicación vacías. migrations es la única
tabla que contiene registros. La marca pasa a COMPLETADA.

## Después de instalar (también pendientes de autorización/ejecución)

Volver a usar exclusivamente Artisan y la cuenta runtime original:

```powershell
& C:\php83\php.exe artisan migrate:status --env=demostracion
```

Solo cuando estén todas las migraciones aplicadas y las tablas de aplicación vacías:

```powershell
& C:\php83\php.exe artisan caja:preparar-demo --env=demostracion --acepto-base=cafeteria_defensa_demo
& C:\php83\php.exe artisan serve --env=demostracion --host=127.0.0.1 --port=8013
```

El preparador crea la cuenta ficticia defensa.demo y muestra su contraseña nueva
localmente una sola vez. No copiar esa salida en chat, capturas o informes. No
reejecutar para recuperar una contraseña ni limpiar una base poblada.

Abrir http://127.0.0.1:8013, crear un pedido y probar QR simulado aprobado. Crear
otro y probar tarjeta rechazada, luego un reintento aprobado; comprobar una sola
venta por pedido e historial. El guion completo está en CU26_FINAL_VALIDACION.md.

## Pruebas ejecutadas en esta preparación

- InstalacionDemoTest: 5 pruebas, 55 aserciones, sin fallos. Pruebas puras de destino,
  identidad, grants, inventario y manifiesto, sin arrancar Laravel ni abrir PDO.
- No se ejecutó la suite Feature: construye esquemas SQLite y esta tarea prohíbe
  ejecutar migraciones antes de la revisión.
- Consulta PDO real de lectura con runtime: servidor disponible, cero tablas/vistas
  visibles. Fue necesaria autorización fuera del sandbox; dentro devolvía 2002.
- Inspección completa con instalador e instalación DDL real: PENDIENTES. Las pruebas
  puras no certifican que las migraciones se hayan aplicado en MariaDB.
