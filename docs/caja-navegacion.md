# Etapa 4.2: navegación y presentación

## Cambios

- Menú Caja filtrado mediante `tienePermiso('consultar_caja')`, con entradas a listado y previsualización.
  La sección se despliega en las cuatro rutas de caja. Las rutas y la autorización central de 4.1 no cambian.
- Navegación interna a listado, previsualización, detalle e historial mediante ancla identificada.
- Etiquetas textuales para oficial, histórico no oficial, versión vigente e histórica conservada.
- Aviso accesible: «Las operaciones financieras de caja todavía no están habilitadas».
- Tarjetas y filtros adaptables; tablas con todas sus columnas, desplazamiento horizontal y foco de teclado.
- Paginación con etiquetas en español y enlaces anterior/siguiente que conservan filtros y paginadores.
- CSS exclusivo `public/css/caja.css`, acotado a `.caja-panel`, cargado mediante `@stack('styles')`.
  No se alteran los estilos base ni se añade JavaScript de producto.

Las ocho vistas de 4.1 se ajustan y se agregan `_navegacion` y `_paginacion`. Se modifican el menú,
el catálogo de iconos, el punto de inserción de estilos del layout y las pruebas CajaWebTest.
No se modifican controladores, rutas, modelos, servicios financieros, auditoría ni GET_LOCK.
No se agregan atributos de borradores ni acceso a localStorage o sessionStorage.

## Verificación

CajaWebTest mantiene SQLite :memory: comprobado y amplía la cobertura de menú por permiso,
servidor protegido, enlaces, etiquetas, paginación, estructuras accesibles y ausencia de borradores.
La prueba de renderizado puede exportar seis HTML sintéticos a una carpeta temporal existente:

```powershell
$env:DATABASE_URL = ''
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_FOREIGN_KEYS = 'true'
$cajaFixtures = Join-Path ([System.IO.Path]::GetTempPath()) 'karma-caja-responsive-42'
New-Item -ItemType Directory -Path $cajaFixtures -Force | Out-Null
$env:KARMA_CAJA_HTML_DIR = $cajaFixtures
& C:\php83\php.exe vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --do-not-cache-result --filter CajaWebTest
Remove-Item Env:KARMA_CAJA_HTML_DIR
```

`tests/Browser/caja-responsive.cjs` requiere Node.js y Playwright disponibles (NODE_PATH puede apuntar
a su instalación existente). Usa Chromium headless o Edge instalado con KARMA_CAJA_BROWSER=msedge.
No descarga navegadores. Todas las solicitudes se interceptan y sirven desde los HTML sintéticos y
los assets locales, sin iniciar Laravel o acceder a bases. No se usan datos personales reales.

```powershell
$env:KARMA_CAJA_BROWSER = 'msedge'
node tests/Browser/caja-responsive.cjs $cajaFixtures
```

Comprueba seis pantallas a 320, 375, 768 y 1440 píxeles: sin desbordamiento de página, tablas
desplazables hasta la última columna, importes no ocultos y almacenamiento del navegador vacío.
Comprueba apertura del menú móvil y genera detalle-375.png en la carpeta temporal para inspección.
El resultado esperado es 24 comprobaciones PASS. Es una prueba de presentación aislada, no una
certificación de concurrencia MariaDB o del ciclo completo de sesión en un navegador real.

## Antes de 4.3

Mantener el gate real en false y las cuatro rutas GET. Definir separadamente la publicación de
formularios, CSRF efectivo en pruebas, validación, respuestas inciertas y protección frente a
reenvíos, antes de autorizar cualquier operación financiera. Los 33 PASS de MariaDB anteriores
siguen como referencia; esta etapa no ejecuta ni cambia el arnés de concurrencia.
