# Carga inicial de datos

La interfaz Laravel y phpMyAdmin acceden a la misma base MySQL. phpMyAdmin es
una herramienta para administrarla; no es la conexión entre Laravel y los datos.
Los seeders solo se ejecutan al invocarlos, no al abrir la interfaz ni al guardar.

`php artisan db:seed` carga únicamente roles, permisos y métodos de pago
(Efectivo, QR y Mixto). Se pueden repetir sin duplicar estos registros ni
sobrescribir sus atributos existentes. Los permisos predeterminados se asignan
solo a los roles creados en esa ejecución; se conservan las asignaciones de los
roles existentes, incluso si se les quitaron todos los permisos.

No crea usuarios ni un administrador con contraseña predeterminada. En una
instalación nueva se debe crear una cuenta administrativa por el procedimiento
de administración del proyecto.

Los demás seeders son ejemplos históricos y no forman parte de la carga normal.
No ejecutarlos individualmente en una base en uso: contienen IDs fijos,
contraseñas de ejemplo y datos de operaciones que no representan el flujo actual
(incluido un usuario con rol 4 inexistente y promociones con formatos antiguos).
Los productos, usuarios, existencias, pedidos y ventas reales se gestionan desde
la interfaz. Modificar un seeder no modifica los registros que ya están en MySQL.

No usar `migrate:fresh --seed` sobre la base actual: elimina las tablas y sus datos.
