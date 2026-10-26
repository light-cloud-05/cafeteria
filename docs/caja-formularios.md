# Etapa 4.3: formularios protegidos (producción bloqueada)

Se agregan cuatro rutas explícitas, manteniendo las cuatro consultas anteriores:

| Método y ruta | Nombre | Permisos adicionales a auth |
| --- | --- | --- |
| GET /admin/caja/confirmacion | admin.caja.confirmacion | consultar_caja + confirmar_cierre_caja |
| POST /admin/caja/cierres | admin.caja.confirmar | consultar_caja + confirmar_cierre_caja |
| GET /admin/caja/cierres/{cierre}/rectificacion | admin.caja.rectificacion | consultar_caja + rectificar_cierre_caja |
| POST /admin/caja/cierres/{cierre}/rectificaciones | admin.caja.rectificar | consultar_caja + rectificar_cierre_caja |

No hay binding automático de cierres, CRUD genérico, PUT/PATCH/DELETE ni acceso al controlador antiguo.
Caja continúa dentro de P4. Los enlaces a formularios se muestran según permisos individuales.

## Bloqueo efectivo

`CoordinadorCobrosCaja::permiteCierreDefinitivo()` no se modifica: sigue devolviendo false.
`DisponibilidadCajaWeb` consulta ese gate. El único reemplazo permitido requiere simultáneamente
PHP CLI, ejecución PHPUnit (constante PHPUNIT_COMPOSER_INSTALL), entorno testing, SQLite :memory:
sin URL alternativa, conexión real de ese tipo y PRAGMA sin archivos adjuntos persistentes,
y un coordinador de pruebas inyectado explícitamente que permita la operación.
No hay parámetro HTTP, sesión o configuración para habilitar operaciones reales.

Los formularios se pueden consultar con autorización, pero muestran fieldset deshabilitado y el aviso
de producción. Un POST autorizado con CSRF válido recibe 503 antes de llamar al servicio financiero
si el gate está cerrado. No se generan cierres, versiones ni eventos financieros en ese rechazo.

## Entrada y resultados

Confirmar acepta fecha, efectivo_contado y observacion; rectificar acepta efectivo_contado,
observacion, motivo y version_esperada. Además se admite el token CSRF. Cualquier otro campo se
rechaza, incluidos totales, diferencia, actor y fecha en rectificación. Se validan tipos, fechas,
límites de texto y dinero exacto mediante ImporteCaja, sin conversiones float.
El actor siempre procede de AutorizacionCaja y los servicios existentes.

La capa HTTP no inicia transacciones ni toma bloqueos: delega a los servicios, que recalculan o
conservan instantáneas, revalidan permisos después de esperar, verifican versión y auditan.
No se modifica el orden de bloqueo, GET_LOCK, liberación o número de intentos transaccionales.

Revisión 4.4: una ValidationException también puede producirse después del commit. Si el servicio
ya fue invocado, el HTTP 422 conserva la advertencia de resultado no confirmado y exige consultar
estado, sin afirmar rechazo o rollback. Las validaciones HTTP anteriores al servicio sí se muestran
como rechazadas. Esto puede generar advertencias conservadoras incluso en rechazos reales del
servicio; evita inferir el estado transaccional por el tipo de excepción.
En respuestas POST de caja, el botón global Actualizar se sustituye por un enlace GET a Consultar
estado, evitando que esa acción de la interfaz vuelva a enviar el POST.

Se usa CSRF existente sin excepciones de rutas. Las pruebas sustituyen únicamente su omisión
automática de PHPUnit, comprobando tokens ausentes, incorrectos y correctos en ambos POST.
Una validación rechazada devuelve 422 con mensajes controlados por campo. No se copian a la
sesión entradas inválidas, motivos con secretos o mensajes de excepciones. Debe consultarse el
estado y abrir un formulario nuevo cuando corresponda; no se reenvía automáticamente.
Permisos revocados mantienen la respuesta de autorización; versiones obsoletas se rechazan.

Un fallo de coordinación incierto devuelve 409; un fallo de adquisición conocido, 503. El timeout
ValidationException del coordinador mantiene 422 con aviso de caja ocupada. Una excepción genérica
se trata conservadoramente como resultado sin confirmar (409), porque puede ocurrir después del
commit. No se afirma rollback ni se vuelven a ejecutar servicios. Los éxitos redirigen al detalle.
Los eventos confirmados y de incidente siguen siendo responsabilidad exclusiva de los servicios;
los rechazos de middleware y validación HTTP no agregan una segunda auditoría financiera.

Todos los mensajes y campos se escapan con Blade. No se agregan borradores, JavaScript financiero,
localStorage, sessionStorage o cachés HTTP de respuestas financieras (private, no-store).

## Archivos y verificación

- OperacionesCajaController, DisponibilidadCajaWeb y cuatro rutas explícitas.
- confirmar.blade.php, rectificar.blade.php, _campos-conteo.blade.php y resultado.blade.php.
- Enlaces en _navegacion y estilos de fieldset acotados a caja.css.
- CajaFormulariosTest, ajuste de la prueba de las cuatro consultas en CajaWebTest y ampliación
  del arnés visual para incluir cuatro renderizados de formularios.

La suite usa solo SQLite en memoria y simulaciones; no se ejecuta ninguna migración persistente.
Las pruebas de dominio previas siguen verificando las instantáneas y rollback. Las pruebas HTTP
añaden CSRF real, permisos, manipulación, dinero/fechas, versiones obsoletas, timeout, revocación,
incertidumbre posterior al commit, bloqueo sin auditorías confirmadas y formularios escapados.

Para regenerar HTML sintético, usar las variables SQLite y KARMA_CAJA_HTML_DIR de
`caja-navegacion.md`, ejecutar toda la suite y después el arnés visual. Con las cuatro exportaciones
de formularios presentes, el arnés comprueba 10 pantallas × 4 anchos = 40 comprobaciones.
Solo hay peticiones interceptadas contra archivos sintéticos; no accede a Laravel o MariaDB.

## Pendiente antes de habilitar

Los 33 PASS MariaDB anteriores no certifican esta nueva capa HTTP. No se repitieron aquí.
La activación real requiere autorización separada, revisión de despliegue/sesiones y CSRF en un
entorno aislado, conservación de la regresión MariaDB y procedimiento de reconciliación de
respuestas inciertas. Este trabajo no activa ningún cierre o rectificación en producción.
