-- Consultas documentales para el esquema de script_informe.sql.
USE sistema_inventario;
-- Fechas operativas almacenadas en UTC por FechaLocal; Bolivia usa UTC-04:00.
-- Inicializar estos criterios cada vez que se ejecute el bloque de consultas.
SET @ahora_bolivia = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 4 HOUR);
SET @hoy_bolivia = DATE(@ahora_bolivia);
SET @inicio_dia_utc = DATE_ADD(TIMESTAMP(@hoy_bolivia), INTERVAL 4 HOUR);
SET @fin_dia_utc = DATE_ADD(@inicio_dia_utc, INTERVAL 1 DAY);
SET @inicio_mes_local = CAST(DATE_FORMAT(@hoy_bolivia, '%Y-%m-01') AS DATE);
SET @inicio_mes_utc = DATE_ADD(TIMESTAMP(@inicio_mes_local), INTERVAL 4 HOUR);
SET @fin_mes_utc = DATE_ADD(@inicio_mes_utc, INTERVAL 1 MONTH);
-- Criterio de ejemplo para la consulta 12; reemplazar por el cierre requerido.
SET @id_cierre = 1;

-- CONSULTAS SIMPLES (1 a 10)
-- 1. Productos marcados como disponibles; la venta tambien valida categoria y stock.
SELECT * FROM productos WHERE disponible = TRUE;

-- 2. Existencias en el minimo o por debajo de el.
SELECT * FROM stocks WHERE stock_actual <= stock_minimo;

-- 3. Usuarios activos que no han sido eliminados logicamente.
SELECT id, nombreCompleto, nombreUsuario FROM usuarios
WHERE estado = TRUE AND deleted_at IS NULL;

-- 4. Categorias activas.
SELECT * FROM categorias WHERE estado = TRUE;

-- 5. Proveedores activos.
SELECT id, nombre, telefono FROM proveedores WHERE estado = TRUE;

-- 6. Promociones vigentes, con el formato admitido por la aplicacion.
SELECT * FROM promociones
WHERE activo = TRUE
  AND tipo IN ('Porcentaje', 'Monto fijo')
  AND (aplicaSobre = 'Pedido' OR (aplicaSobre = 'Producto'
       AND (JSON_LENGTH(producto_ids) > 0 OR producto_id IS NOT NULL)))
  AND @hoy_bolivia BETWEEN fechaInicio AND fechaFin
  AND (horaInicio IS NULL OR TIME(@ahora_bolivia) >= horaInicio)
  AND (horaFin IS NULL OR TIME(@ahora_bolivia) <= horaFin);

-- 7. Catalogo de metodos de pago.
SELECT * FROM metodo_pagos;

-- 8. Actividad registrada durante el dia boliviano.
SELECT * FROM bitacoras
WHERE fechaHora >= @inicio_dia_utc AND fechaHora < @fin_dia_utc;

-- 9. Pedidos registrados; la ausencia de venta se verifica en la consulta 15.
SELECT * FROM pedidos WHERE estado = 'Registrado';

-- 10. Ventas realizadas exclusivamente en efectivo.
SELECT * FROM ventas WHERE metodo_pago = 'Efectivo' AND estado = 'Completado';

-- CONSULTAS MULTIPLES CON JOIN (11 a 20)
-- 11. Ventas completadas del mes, agrupadas por responsable.
SELECT u.id, u.nombreCompleto, COUNT(v.id) AS cantidadVentas,
       SUM(v.total) AS totalVendidoMes
FROM ventas v JOIN usuarios u ON u.id = v.usuario_id
WHERE v.estado = 'Completado'
  AND v.fecha_hora >= @inicio_mes_utc AND v.fecha_hora < @fin_mes_utc
GROUP BY u.id, u.nombreCompleto;

-- 12. Cierre de caja y su responsable; no existe una relacion ventas.idCierre.
SELECT c.id, u.nombreCompleto AS responsable, c.fechaFin,
       c.totalSistema, c.totalDeclarado, c.diferencia
FROM cierre_cajas c JOIN usuarios u ON u.id = c.idUsuario
WHERE c.id = @id_cierre;

-- 13. Productos, categorias y existencias, incluidos productos sin stock registrado.
SELECT p.id, p.nombre AS producto, c.nombre AS categoria,
       s.stock_actual, s.stock_minimo
FROM productos p JOIN categorias c ON c.id = p.categoria_id
LEFT JOIN stocks s ON s.producto_id = p.id;

-- 14. Ventas y responsable del cobro; el metodo se almacena como texto.
SELECT v.id, u.nombreCompleto AS atendidoPor, v.metodo_pago,
       v.total, v.monto_efectivo, v.monto_qr, v.fecha_hora
FROM ventas v JOIN usuarios u ON u.id = v.usuario_id;

-- 15. Detalles de pedidos registrados que aun no tienen venta.
SELECT dp.pedido_id, pr.nombre AS producto, dp.cantidad,
       dp.precioUnitario, dp.subtotal
FROM detalle_pedidos dp
JOIN productos pr ON pr.id = dp.producto_id
JOIN pedidos p ON p.id = dp.pedido_id
LEFT JOIN ventas v ON v.pedido_id = p.id
WHERE p.estado = 'Registrado' AND v.id IS NULL;

-- 16. Movimientos con producto, proveedor opcional y usuario responsable.
SELECT m.id, m.tipo, pr.nombre AS producto, prov.nombre AS proveedor,
       u.nombreUsuario AS realizadoPor, m.cantidad, m.fecha_hora
FROM movimiento_inventarios m
JOIN productos pr ON pr.id = m.producto_id
LEFT JOIN proveedores prov ON prov.id = m.proveedor_id
JOIN usuarios u ON u.id = m.usuario_id;

-- 17. Permisos asignados a cada rol.
SELECT r.nombre AS rol, p.nombre AS permiso, p.descripcion
FROM roles r JOIN rol_permisos rp ON rp.idRol = r.rol_id
JOIN permisos p ON p.id = rp.idPermiso;

-- 18. Recaudacion mensual por responsable, separada por efectivo y QR.
SELECT u.id, u.nombreCompleto, COUNT(v.id) AS cantidadVentas,
       SUM(v.total) AS totalRecaudado,
       SUM(v.monto_efectivo) AS totalEfectivo, SUM(v.monto_qr) AS totalQR
FROM ventas v JOIN usuarios u ON u.id = v.usuario_id
WHERE v.estado = 'Completado'
  AND v.fecha_hora >= @inicio_mes_utc AND v.fecha_hora < @fin_mes_utc
GROUP BY u.id, u.nombreCompleto;

-- 19. Ventas por metodo; conservar metodos historicos aunque no esten en el catalogo.
SELECT v.metodo_pago, mp.id AS catalogo_id, COUNT(v.id) AS numTransacciones,
       SUM(v.total) AS total, SUM(v.monto_efectivo) AS efectivo, SUM(v.monto_qr) AS qr
FROM ventas v
LEFT JOIN (SELECT nombre, MIN(id) AS id FROM metodo_pagos GROUP BY nombre) mp
  ON mp.nombre = v.metodo_pago
WHERE v.estado = 'Completado'
GROUP BY v.metodo_pago, mp.id;

-- 20. Cinco productos mas vendidos: contar unicamente pedidos con venta completada.
SELECT p.id, p.nombre, SUM(dp.cantidad) AS totalUnidadesVendidas
FROM detalle_pedidos dp JOIN productos p ON p.id = dp.producto_id
JOIN ventas v ON v.pedido_id = dp.pedido_id
WHERE v.estado = 'Completado'
GROUP BY p.id, p.nombre ORDER BY totalUnidadesVendidas DESC, p.id LIMIT 5;

-- SUBCONSULTAS (21 a 30)
-- 21. Productos con precio superior al promedio de su categoria.
SELECT p1.nombre, p1.precio, p1.categoria_id FROM productos p1
WHERE p1.precio > (SELECT AVG(p2.precio) FROM productos p2
                  WHERE p2.categoria_id = p1.categoria_id);

-- 22. Usuarios no eliminados que nunca han registrado una venta completada.
SELECT u.id, u.nombreCompleto, u.nombreUsuario FROM usuarios u
WHERE u.deleted_at IS NULL AND NOT EXISTS (
    SELECT 1 FROM ventas v WHERE v.usuario_id = u.id AND v.estado = 'Completado'
);

-- 23. Productos que nunca han sido vendidos.
SELECT p.id, p.nombre FROM productos p WHERE NOT EXISTS (
    SELECT 1 FROM detalle_pedidos dp JOIN ventas v ON v.pedido_id = dp.pedido_id
    WHERE dp.producto_id = p.id AND v.estado = 'Completado'
);

-- 24. Categorias con mas de cinco productos.
SELECT nombre FROM categorias WHERE id IN (
    SELECT categoria_id FROM productos GROUP BY categoria_id HAVING COUNT(*) > 5
);

-- 25. Productos con el precio maximo del catalogo.
SELECT nombre, precio FROM productos WHERE precio = (SELECT MAX(precio) FROM productos);

-- 26. Responsables con algun cierre que presenta faltante.
SELECT u.id, u.nombreCompleto FROM usuarios u WHERE EXISTS (
    SELECT 1 FROM cierre_cajas c WHERE c.idUsuario = u.id AND c.diferencia < 0
);

-- 27. Proveedores con mas de tres movimientos de entrada.
SELECT nombre, telefono FROM proveedores WHERE id IN (
    SELECT proveedor_id FROM movimiento_inventarios
    WHERE tipo = 'entrada' AND proveedor_id IS NOT NULL
    GROUP BY proveedor_id HAVING COUNT(*) > 3
);

-- 28. Ventas completadas con importe superior al promedio de ventas completadas.
SELECT id, total, fecha_hora FROM ventas
WHERE estado = 'Completado'
  AND total > (SELECT AVG(total) FROM ventas WHERE estado = 'Completado');

-- 29. Usuarios que registraron pedidos en las ultimas 168 horas.
SELECT u.id, u.nombreCompleto FROM usuarios u WHERE EXISTS (
    SELECT 1 FROM pedidos p WHERE p.usuario_id = u.id
      AND p.fechaHora >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
      AND p.fechaHora <= UTC_TIMESTAMP()
);

-- 30. Promociones aplicadas a diez o mas pedidos con venta completada.
SELECT pr.id, pr.nombre, pr.tipo, pr.valor FROM promociones pr WHERE pr.id IN (
    SELECT p.promocion_id FROM pedidos p JOIN ventas v ON v.pedido_id = p.id
    WHERE p.promocion_id IS NOT NULL AND v.estado = 'Completado'
    GROUP BY p.promocion_id HAVING COUNT(*) >= 10
);
