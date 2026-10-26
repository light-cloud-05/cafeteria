# Fase 5 — Consultas de consolidación mensual y anual

## Análisis y arquitectura

No se requieren tablas, columnas, migraciones o permisos nuevos. Se conservan cierre_cajas,
cierre_caja_versiones y cierre_caja_version_ventas. El índice único de fecha operativa y la
unicidad cierre/número de versión permiten seleccionar exactamente una versión vigente por día.
La relación CierreCaja::versionVigente utiliza el máximo numero_version; el consolidado replica
ese criterio en una sola sentencia SELECT mediante JOIN y NOT EXISTS, no por fecha de registro.

Los importes proceden exclusivamente de la versión persistida, y la cantidad de ventas de sus
instantáneas. No se leen ventas ni pedidos actuales para reconstruir ingresos. No se consultan
ni suman totalSistema/totalDeclarado heredados. Las cabeceras con fecha operativa NULL se excluyen.
Las rectificaciones anteriores permanecen intactas y no se cuentan otra vez.

ConsolidacionCajaService::mensual(anio, mes) y ::anual(anio) verifican AutorizacionCaja::consulta()
antes de cualquier SELECT financiero. El anual hace una sola lectura para los doce meses y
construye los meses con el mismo conjunto de filas y la misma fecha de corte en America/La_Paz.
Así una rectificación concurrente no mezcla doce consultas independientes dentro de una respuesta.
No se adquiere GET_LOCK ni se inicia una transacción de escritura para consultar el consolidado.

## Cálculos y estados

TotalesCajaPeriodo utiliza Brick Math BigDecimal, ya utilizado por Laravel e instalado en vendor.
No se agrega una dependencia ni se convierte dinero a float. Las columnas DECIMAL se seleccionan
como texto. Se suman total_ventas, efectivo_esperado, qr_registrado, efectivo_contado y diferencia;
los acumulados no se limitan a la capacidad de un solo cierre diario.

Se verifica la coherencia de los valores persistidos: total_ventas = efectivo_esperado + qr_registrado
y diferencia = efectivo_contado - efectivo_esperado. La diferencia persistida se conserva, incluyendo
su signo. Además se suman faltantes negativos y sobrantes positivos separadamente, y se cuentan
días con diferencia: dos diferencias opuestas no convierten el período en conciliado.
ImporteConsolidadoVista formatea cadenas de cualquier longitud necesaria sin redondeos ni float.

SQLite representa su columna generada diferencia mediante afinidad REAL y puede devolver texto como
1.0099999999999998. Solo en SQLite se normaliza ese texto con BigDecimal a escala 2 y HalfUp,
igual que el cast decimal:2 existente en Eloquent. MariaDB conserva su DECIMAL exacto sin ese ajuste.
La precisión máxima con fracciones se comprueba también mediante entradas de cadenas exactas,
porque SQLite no puede certificar el almacenamiento DECIMAL de MariaDB.

Cobertura y conciliación son estados distintos:

- Cada fecha anterior a hoy en Bolivia es exigible. Sin cierre oficial: pendiente; con cierre cero
  válido: cerrado, incluso sin ventas. No se infieren ceros a partir de ausencia de cierres.
- Hoy: abierto. Fechas posteriores: futuras. No se cuentan como fechas atrasadas sin cierre.
- Mes completo: todos sus días ya terminaron y tienen cierres válidos. Mes actual: incompleto/en curso.
  Mes futuro: futuro. El año es completo solo con sus doce meses completos; futuro si aún no empezó.
- Sin cierres válidos: sin_cierres. Con cualquier diferencia diaria: con_diferencias, aunque la neta
  sea cero. Conciliado exige cobertura completa y ninguna diferencia diaria. Un período parcial
  sin diferencias solo se identifica como sin_diferencias_registradas.
- Mayor numero_version > 1 identifica un día rectificado. Los enlaces permiten consultar la versión
  incluida y el historial del cierre sin modificarlo.

Sin calendario de apertura configurado, se consideran todos los días calendario terminados del
período solicitado, incluyendo fines de semana y días sin ventas. Es una regla conservadora visible
en las pantallas; un calendario de cierres por feriados o fecha de inicio del negocio requeriría
una decisión funcional posterior. No se cambia automáticamente esta regla ni se habilitan escrituras.

Una cabecera oficial sin versión, valores incoherentes o cierre registrado para hoy/futuro se señala
como inconsistente. No se suma como válido ni se presenta cero: los totales del mes afectado quedan
no disponibles, y también el total anual si incluye ese mes. Los demás meses y días siguen visibles.
Si se detectaran fechas oficiales duplicadas pese al índice, se rechaza la lectura en lugar de sumarlas.

## Interfaz y permisos

- GET /admin/caja/mensual?anio=2026&mes=10 → admin.caja.mensual.
- GET /admin/caja/anual?anio=2026 → admin.caja.anual.

ConsolidadosCajaController utiliza web, auth y permiso:consultar_caja, y vuelve a autorizar en
controlador y servicio. Se validan año 1000–9999 y mes 1–12. Por defecto usa el período actual
de Bolivia. No hay bindings financieros anticipados ni rutas nuevas de escritura.
Las respuestas son private, no-store; errores internos no muestran SQL ni trazas.

Las vistas mensual/anual y el parcial _resumen-periodo reutilizan el layout, caja.css, tablas
desplazables y etiquetas existentes. Hay filtros GET, cobertura, conciliación, totales, fechas
pendientes, meses futuros y enlaces a detalle diario e historial. Los textos se escapan con Blade;
no se agregan JavaScript financiero, borradores o almacenamiento local.
Los dos accesos pertenecen a P4 — Pedidos, Ventas y Caja y requieren consultar_caja por separado.
Se conservan únicamente P1–P6 y el aviso de operaciones financieras deshabilitadas.

## Archivos de esta fase

Nuevos: ConsolidacionCajaService.php, TotalesCajaPeriodo.php, ImporteConsolidadoVista.php,
ConsolidadosCajaController.php, mensual.blade.php, anual.blade.php, _resumen-periodo.blade.php,
ConsolidadosCajaTest.php y este documento.
Modificados: routes/web.php (dos GET), menú P4, _navegacion, CajaFormulariosTest (diez rutas en
vez de ocho, manteniendo verificaciones de permisos) y el arnés visual (dos pantallas adicionales).
No se modifican servicios diarios, modelos, cálculos de cobro, transacciones, auditoría o GET_LOCK.

## Pruebas y límites

ConsolidadosCajaTest añade 18 casos con usuarios, roles y permiso reales. Comprueba SQLite :memory:
y PRAGMA sin archivos antes de preparar fixtures. Incluye ausencia de cierres, períodos incompletos,
futuros, día abierto, bisiesto completo, cierres cero, varias versiones, efectivo/QR/mixto persistidos,
diferencias de signos opuestos, máximos exactos, históricos NULL, coherencia entre días/meses/año,
autorización, validaciones, consultas sin escrituras, errores seguros y presentación.

Suite completa: 196 pruebas, 195 aprobadas, una omitida por requerir MariaDB real, 2866 aserciones.
Arnés visual: 48/48 combinaciones (12 pantallas × 320/375/768/1440 píxeles), HTML sintético y assets
locales interceptados. Sin tráfico a Laravel o a bases persistentes.

No se ejecutó el arnés MariaDB ni se accedió a cafeteria_pro, cafeteria_pro_pruebas,
cafeteria_caja_integracion o AWS. Los 33 PASS aportados por el operador son referencia previa.
Los archivos nuevos de servicios están incluidos por glob en la huella del arnés: la huella cambiará
al repetirlo, aunque sus 11 escenarios y el protocolo de coordinación no se modifican.
Antes de activar operaciones reales hace falta autorización separada y nueva verificación aislada.

Cada respuesta anual es consistente dentro de su única sentencia financiera; dos consultas realizadas
en momentos distintos pueden mostrar versiones distintas si hubo rectificación entre ambas. La fecha
de corte identifica el día operativo, no una congelación permanente o nueva versión del consolidado.
El gate de producción permanece false y no se generan bitácoras financieras al consultar.
