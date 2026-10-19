-- Poblacion inicial para el informe. Usar SOLO en las tablas vacias de una
-- instalacion nueva creada con script_informe.sql. No ejecutar junto con db:seed.
-- Para el proyecto Laravel, el mecanismo habitual es: php artisan db:seed
-- No se eliminan datos ni se desactivan las claves foraneas.
USE sistema_inventario;
START TRANSACTION;

INSERT INTO roles (nombre, estado, created_at, updated_at) VALUES
('Administrador', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Cajero', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Mesero', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO permisos (nombre, descripcion, created_at, updated_at) VALUES
('gestion_usuarios', 'Administrar cuentas de usuarios', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('gestion_roles', 'Administrar roles y permisos', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('gestion_categorias', 'Administrar categorías', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('gestion_productos', 'Administrar productos', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('gestion_proveedores', 'Administrar proveedores', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('registrar_entradas', 'Registrar entradas de inventario', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('registrar_salidas', 'Registrar salidas de inventario', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('registrar_ajustes', 'Registrar ajustes de inventario', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('consultar_stock', 'Consultar existencias actuales', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('consultar_stock_minimo', 'Consultar productos con stock mínimo', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('buscar_productos', 'Buscar productos en el catálogo', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('ver_agotados', 'Consultar avisos de productos agotados', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('registrar_pedidos', 'Registrar pedidos', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('modificar_pedidos', 'Modificar o cancelar pedidos sin cobrar', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('cambiar_estado_pedidos', 'Actualizar el estado de los pedidos', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('cobrar_ventas', 'Registrar ventas y pagos', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('consultar_historial_pedidos', 'Consultar historial de pedidos', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('ver_reportes', 'Consultar reportes de ventas e inventario', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('gestion_promociones', 'Administrar promociones y descuentos', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('consultar_bitacora', 'Consultar la bitácora del sistema', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO metodo_pagos (nombre, created_at, updated_at) VALUES
('Efectivo', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('QR', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Mixto', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO rol_permisos (idRol, idPermiso, fechaAsignacion, created_at, updated_at)
SELECT r.rol_id, p.id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM roles r CROSS JOIN permisos p
WHERE (r.nombre = 'Administrador' AND p.nombre IN (
    'gestion_usuarios', 'gestion_roles', 'gestion_categorias', 'gestion_productos',
    'gestion_proveedores', 'registrar_entradas', 'registrar_salidas', 'registrar_ajustes',
    'consultar_stock', 'consultar_stock_minimo', 'buscar_productos', 'ver_agotados',
    'consultar_historial_pedidos', 'ver_reportes', 'gestion_promociones', 'consultar_bitacora'
)) OR (r.nombre = 'Cajero' AND p.nombre IN (
    'registrar_salidas', 'consultar_stock', 'consultar_stock_minimo', 'ver_agotados', 'cobrar_ventas'
)) OR (r.nombre = 'Mesero' AND p.nombre IN (
    'consultar_stock', 'buscar_productos', 'ver_agotados', 'registrar_pedidos',
    'modificar_pedidos', 'cambiar_estado_pedidos', 'consultar_historial_pedidos'
));

COMMIT;
-- Resultado inicial: 3 roles, 20 permisos, 3 metodos de pago y 28 asignaciones.
-- No se crean usuarios, productos, existencias ni operaciones ficticias.
