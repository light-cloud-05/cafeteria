# Caja: catálogo y autorización (fase 3.1)

El catálogo está definido en PermisoSeeder. Registrarlo en una base persistente y asignarlo a roles
requiere una operación posterior autorizada. Esta fase solo lo ejecuta en SQLite en memoria.
No cambia roles predeterminados, rutas, bitácora ni la habilitación del cierre.

## Operaciones

| Operación | Permisos exigidos |
|---|---|
| Consultar/previsualizar | consultar_caja |
| Confirmar | consultar_caja y confirmar_cierre_caja |
| Autorizar una futura rectificación | consultar_caja y rectificar_cierre_caja |

AutorizacionCaja utiliza usuarios reales releídos de la base y User::tienePermiso(). No confía
en el estado o la relación rol cacheados en Auth::user(), ni concede excepciones por nombre del rol.
VerificarPermiso sigue utilizando el mismo catálogo; las entradas HTTP futuras deberán combinar
su middleware con la comprobación en el servicio. No se publicaron rutas en esta fase.

## Confirmación y orden de bloqueos

1. Releer actor/rol y autorizar antes de consultar ventas o adquirir GET_LOCK.
   Se rechazan transacciones preexistentes del llamador para no heredar snapshots antiguas.
2. Mantener el mismo coordinador y el mismo nombre global de bloqueo.
3. Tras adquirir la coordinación, iniciar la transacción de un solo intento.
4. Bloquear primero el rol previamente autorizado y después el usuario. Este orden evita invertir
   el de RolController::update(), que bloquea el rol antes de sincronizar permisos.
5. Rechazar si cambió la identidad o el rol; no perseguir otro rol bajo los bloqueos ya adquiridos.
6. Revalidar actividad y permisos. Una lectura FOR UPDATE del permiso y su pivote impide aceptar
   una concesión revocada que aún aparezca en una snapshot anterior de InnoDB.
7. Solo entonces consultar y escribir los datos financieros existentes. Conservar transacción,
   instantáneas, auditoría original y liberación del mutex después del commit/rollback.

Las lecturas bloqueadas de SQLite no equivalen a bloqueos InnoDB. Las pruebas ordinarias validan
la secuencia y los rechazos; el código nuevo requiere regresión real posterior en MariaDB.
No se implementa aún la operación financiera de rectificar: solo su comprobación central de permisos.

## Asignación inicial propuesta, no ejecutada

- Operación de caja: consulta y confirmación, si la persona responsable está autorizada para cerrar.
- Supervisión: consulta y rectificación; confirmación solo si también cumple esa función.
- Auditoría financiera: consulta. consultar_bitacora se concede separadamente si corresponde.

Un administrador autorizado debe elegir los roles concretos por su identidad en la pantalla existente
de Roles y permisos y conservar las demás casillas. No ejecutar sincronizaciones de defaults por nombre,
ni asignar por llamarse Administrador/Cajero. Antes, registrar el catálogo en una conexión verificada y
autorizada mediante PermisoSeeder, sin ejecutar DatabaseSeeder para restablecer asignaciones.

PermisoSeeder::firstOrCreate conserva IDs y descripciones ya configuradas. La carga es idempotente
secuencialmente; como permisos.nombre no tiene UNIQUE en el esquema histórico, no lanzar cargas
concurrentes del catálogo. Añadir esa restricción requeriría análisis de duplicados y autorización aparte.

## Regresión

- Se mantienen los nueve casos del arnés MariaDB y sus 27 PASS previos como referencia histórica.
- El padre del arnés prepara explícitamente permisos para su actor sintético id=1/rol=1, solo en la
  base desechable marcada. Los workers no omiten la nueva autorización del servicio.
- La nueva huella incluye modelos de autorización, middleware y catálogo. Los resultados anteriores
  no certifican esta versión modificada: volver a ejecutar el arnés solo con autorización.
- permiteCierreDefinitivo() continúa devolviendo false. Tener permisos no permite saltar ese bloqueo.

Siguiente etapa: revisar y autorizar la carga/asignación inicial y la nueva regresión; después abordar
rectificaciones y ampliación de bitácora por separado. No modificar la base original, AWS ni publicar rutas
como parte de este trabajo.
