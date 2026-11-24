<?php

declare(strict_types=1);

// No se carga Laravel/Artisan ni se usan variables DB_* heredadas para conectar.
ini_set('display_errors','0');ini_set('log_errors','0');
require dirname(__DIR__).'/vendor/autoload.php';

use App\Services\Caja\AislamientoCajaDemo;
use App\Services\Demostracion\{GuardiaInstalacionDemo, InstaladorEsquemaDemo};
use App\Services\Demostracion\RechazoInstalacionDemo as RuntimeException;

try {
    $argumentos=array_slice($argv,1);
    if ($argumentos === ['--help']) {
        echo "Instalador exclusivo: 127.0.0.1:3308 / cafeteria_defensa_demo / karma_demo_instalador.\n";
        echo "Usar tools/Instalar-Demo.ps1 -Comprobar. La instalación exige -Instalar -AceptoBase cafeteria_defensa_demo.\n";
        exit(0);
    }
    $instalar=$argumentos === ['--instalar','--acepto-base=cafeteria_defensa_demo'];
    if (!$instalar && $argumentos !== ['--comprobar']) throw new RuntimeException('Argumentos no admitidos. Sin escrituras.');
    $raiz=dirname(__DIR__);
    $env=Dotenv\Dotenv::parse(file_get_contents($raiz.'/.env.demostracion'));
    GuardiaInstalacionDemo::configuracion($env);
    $manifiesto=InstaladorEsquemaDemo::manifiesto($raiz.'/database/migrations');
    // Exclusivamente por stdin desde el lanzador con contraseña oculta, nunca argv o env.
    $entrada=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
    if (!is_array($entrada) || array_keys($entrada) !== ['password'] || !is_string($entrada['password']) || $entrada['password'] === '') throw new RuntimeException('Falta la entrada segura del instalador.');
    $pdo=new PDO('mysql:host=127.0.0.1;port=3308;dbname=cafeteria_defensa_demo;charset=utf8mb4',GuardiaInstalacionDemo::CUENTA,$entrada['password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_PERSISTENT=>false,PDO::ATTR_TIMEOUT=>5,PDO::ATTR_EMULATE_PREPARES=>false]);
    unset($entrada);
    // Solo esta sesión instaladora: conservar los modos heredados y añadir modo estricto.
    $modosPrevios=array_values(array_filter(array_map('trim',explode(',',(string)$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn()))));
    $modosSesion=$modosPrevios;
    if (!in_array('STRICT_TRANS_TABLES',$modosSesion,true)) $modosSesion[]='STRICT_TRANS_TABLES';
    $configurarModo=$pdo->prepare('SET SESSION sql_mode = ?');
    $configurarModo->execute([implode(',',$modosSesion)]);
    $modosEfectivos=array_map('trim',explode(',',(string)$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn()));
    if (!in_array('STRICT_TRANS_TABLES',$modosEfectivos,true) || array_diff($modosPrevios,$modosEfectivos)) {
        throw new RuntimeException('No se confirmó el modo estricto de la sesión instaladora o se perdió un modo SQL previo.');
    }
    $inventario=GuardiaInstalacionDemo::inspeccionar($pdo,$env['CAJA_DEMO_SERVIDOR']);
    echo "Identidad y permisos instaladores verificados. Base demo completamente vacía (tablas/vistas/triggers/rutinas/eventos: 0).\n";
    echo 'Migraciones locales a revisar: '.count($manifiesto).". Sin seeders ni fixtures.\n";
    foreach (array_keys($manifiesto) as $archivo) echo basename($archivo).PHP_EOL;
    if (!$instalar) { echo "COMPROBACION DE SOLO LECTURA COMPLETADA. No se instaló ningún esquema.\n";exit(0); }
    InstaladorEsquemaDemo::instalar($pdo,$env['CAJA_DEMO_SERVIDOR'],$raiz,$manifiesto);
    echo "Esquema instalado; tablas de aplicación vacías. No se cargaron fixtures. No reejecutar.\n";
} catch (RuntimeException $e) {
    fwrite(STDERR,$e->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable) {
    // Ni mensajes PDO ni trazas ni grants: pueden contener datos de autenticación.
    fwrite(STDERR,"Instalación/comprobación detenida. Revise destino, permisos, inventario y marca local según la guía. No reintente una instalación parcial. No se muestran detalles sensibles.\n");
    exit(1);
}
