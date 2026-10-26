# Etapa 4.1: consultas web de caja

## Superficie publicada

Cuatro rutas GET (con HEAD implícito de Laravel), bajo `web`, `auth` y `permiso:consultar_caja`:

| Ruta | Nombre | Método del controlador |
| --- | --- | --- |
| /admin/caja | admin.caja.index | CajaController::index |
| /admin/caja/previsualizacion | admin.caja.previsualizacion | CajaController::previsualizacion |
| /admin/caja/cierres/{cierre} | admin.caja.show | CajaController::show |
| /admin/caja/cierres/{cierre}/versiones/{version} | admin.caja.version | CajaController::version |

Los identificadores de ruta son numéricos y se reciben como cadenas, sin binding financiero.
Cada acción ejecuta `AutorizacionCaja::consulta()` antes de buscar cierres o importes.
Las consultas autorizadas no toman GET_LOCK ni llaman confirmación, rectificación o auditoría.
No existen rutas de escritura de caja ni acceso al CRUD antiguo. El gate real continúa en false.

## Pantallas y datos

Las ocho vistas en `resources/views/caja/` son index, previsualizacion, show, version, error,
y los parciales _aviso, _version, _historial. Reutilizan el layout y las clases existentes.
No cambia el menú ni se incorpora JavaScript o guardado de borradores en localStorage.

El listado muestra por defecto cierres oficiales. `tipo=historicos` selecciona fecha operativa NULL;
`tipo=todos` incluye ambos. Los filtros desde/hasta comparan fechas operativas, no timestamps UTC,
y excluyen los registros NULL; combinarlos con tipo historicos se rechaza expresamente.
Se validan fechas reales, orden del rango, tipo, tamaños 10/20/50 y páginas positivas acotadas.
Detalle e historial tienen paginadores independientes, de 20 filas cada uno.

La previsualización utiliza exclusivamente CierreDiarioService, toma hoy de America/La_Paz y se
identifica como provisional, incluso si el día ya tiene cierre. Para un día cerrado enlaza a las
instantáneas conservadas. Las inconsistencias devuelven 422 sin presentar ceros ni sumas parciales.
La fecha futura o inválida sigue la validación del servicio. Se admite consultar el día abierto.

Los detalles utilizan versiones e instantáneas, nunca relaciones a ventas o pedidos actuales.
La versión solicitada se busca mediante la relación del cierre; una versión de otro cierre devuelve 404.
Las cabeceras históricas no se convierten en oficiales ni se les reconstruye una versión.

ImporteCajaVista formatea cadenas DECIMAL, incluidos signos y separadores, sin float ni operaciones
financieras. SQLite no garantiza la precisión completa de DECIMAL: el extremo monetario se prueba
como cadena, y los datos reales de MariaDB continúan usando los casts decimales existentes.
Los textos se escapan con Blade. Las respuestas financieras usan Cache-Control private, no-store.
Los errores internos se sustituyen por un mensaje 503 sin SQL ni trazas, también con debug activo.

## Pruebas y límites

CajaWebTest comprueba SQLite :memory: sin URL alternativa antes de crear fixtures. Cubre acceso,
autorización fresca, filtros, fechas, paginación, instantáneas, aislamiento entre cierres, XSS,
errores, gate y las cuatro rutas GET. Compara tablas antes y después y verifica ausencia de
consultas de escritura y GET_LOCK en las consultas con el actor sintético.

La autenticación existente puede actualizar session_last_activity para sesiones reales con token;
no se alteró ese comportamiento. La garantía de solo lectura de esta etapa se refiere a los
registros financieros, pedidos e inventario. No se ejecutaron peticiones contra una base persistente.

No se agregan pruebas MariaDB ni cambios al arnés de los 33 PASS anteriores: los servicios financieros
y el coordinador permanecen intactos. La integración del menú y la revisión visual se describen
en `caja-navegacion.md` (etapa 4.2); las vistas también se renderizan en las pruebas HTTP.
No se requiere migración ni cambio de permisos. No habilitar operaciones financieras en 4.2.
