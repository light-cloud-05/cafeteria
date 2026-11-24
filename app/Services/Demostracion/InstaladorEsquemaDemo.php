<?php

declare(strict_types=1);

namespace App\Services\Demostracion;

use App\Services\Caja\{AislamientoCajaDemo, CoordinadorCobrosCaja};
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Migrations\{DatabaseMigrationRepository, Migrator};
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use PDO;
use App\Services\Demostracion\RechazoInstalacionDemo as RuntimeException;

/** Proceso CLI independiente: nunca carga bootstrap/app.php ni .env operativo. */
final class InstaladorEsquemaDemo
{
    public static function manifiesto(string $directorio): array
    {
        $archivos=glob($directorio.'/*.php');sort($archivos,SORT_STRING);
        if (!$archivos) throw new RuntimeException('No hay migraciones locales para revisar.');
        $manifiesto=[];
        foreach ($archivos as $archivo) {
            if (is_link($archivo) || !preg_match('/\A[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[a-z0-9_]+\.php\z/',basename($archivo))) {
                throw new RuntimeException('Archivo de migración inesperado.');
            }
            // Comparación interna: nunca se imprimen huellas ni contenido de archivos.
            $manifiesto[$archivo]=hash_file('sha256',$archivo);
        }
        return $manifiesto;
    }

    public static function instalar(PDO $pdo, string $servidor, string $raiz, array $revisado): void
    {
        $nombre=CoordinadorCobrosCaja::nombreBloqueo(AislamientoCajaDemo::BASE);
        $lock=$pdo->prepare('SELECT GET_LOCK(?, 5)');$lock->execute([$nombre]);
        if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Demo ocupada; instalación no iniciada.');
        $marca=$raiz.'/storage/app/instalacion-demo-3308-iniciada.json';
        try {
            GuardiaInstalacionDemo::inspeccionar($pdo,$servidor);
            if (self::manifiesto($raiz.'/database/migrations') !== $revisado) throw new RuntimeException('Las migraciones cambiaron antes de instalar.');
            if (file_exists($marca)) throw new RuntimeException('Ya existe una marca de instalación iniciada. No se reejecuta ni se borra automáticamente.');
            $fh=@fopen($marca,'x');
            if (!$fh) throw new RuntimeException('No se pudo reservar la marca exclusiva; sin escrituras en la base.');
            $contenido=json_encode(['base'=>AislamientoCajaDemo::BASE,'puerto'=>3308,'estado'=>'INICIADA','fecha_utc'=>gmdate('c')],JSON_THROW_ON_ERROR);
            $guardada=fwrite($fh,$contenido) === strlen($contenido) && fflush($fh);
            fclose($fh);
            if (!$guardada) throw new RuntimeException('No se confirmó la marca de inicio. Sin DDL; revisar antes de continuar.');

            $contenedor=new Container;
            $capsula=new Manager($contenedor);
            $capsula->addConnection(['driver'=>'mysql','host'=>'127.0.0.1','port'=>3308,'database'=>AislamientoCajaDemo::BASE,
                'username'=>GuardiaInstalacionDemo::CUENTA,'password'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci',
                'prefix'=>'','engine'=>'InnoDB'],'default');
            $conexion=$capsula->getConnection();
            // Reutilizar la sesión ya verificada. No se ejecuta el conector ni sus SET previos a la guarda.
            $conexion->setPdo($pdo);$conexion->setReadPdo($pdo);
            $conexion->setReconnector(static fn () => throw new RuntimeException('No se permite reconectar la instalación.'));
            $contenedor->instance('db',$capsula->getDatabaseManager());
            $contenedor->instance('db.schema',$conexion->getSchemaBuilder());
            Facade::clearResolvedInstances();Facade::setFacadeApplication($contenedor);
            $conexion->beforeExecuting(function (string $sql) use ($conexion,$pdo,$servidor,$raiz,$revisado) {
                if ($conexion->getRawPdo() !== $pdo || self::manifiesto($raiz.'/database/migrations') !== $revisado
                    || preg_match('/\b(?:cafeteria_pro|cafeteria_pro_pruebas)\b|^\s*(?:USE|GRANT|REVOKE|DROP|TRUNCATE|DELETE|CALL|LOAD|SET\s+(?:GLOBAL|ROLE))\b|\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i',$sql)) {
                    throw new RuntimeException('Operación no admitida por el instalador exclusivo.');
                }
                GuardiaInstalacionDemo::inspeccionar($pdo,$servidor,false);
            });
            $repositorio=new DatabaseMigrationRepository($capsula->getDatabaseManager(),'migrations');
            $repositorio->createRepository();
            $migrador=new Migrator($repositorio,$capsula->getDatabaseManager(),new Filesystem);
            $migrador->run(array_keys($revisado));
            $esperadas=array_map(static fn($p)=>pathinfo($p,PATHINFO_FILENAME),array_keys($revisado));sort($esperadas);
            $aplicadas=$repositorio->getRan();sort($aplicadas);
            if ($esperadas !== $aplicadas) throw new RuntimeException('No se acreditó la aplicación completa del esquema. No reintentar.');
            $inventario=GuardiaInstalacionDemo::inspeccionar($pdo,$servidor,false);
            if ($inventario['triggers'] || $inventario['rutinas'] || $inventario['eventos']) throw new RuntimeException('Objetos inesperados después de instalar.');
            $tablas=$pdo->query('SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tablas as $tabla) {
                if ($tabla['TABLE_TYPE'] !== 'BASE TABLE' || $tabla['ENGINE'] !== 'InnoDB' || !preg_match('/\A[a-z_]+\z/',$tabla['TABLE_NAME'])) throw new RuntimeException('Objeto o motor inesperado después de instalar.');
                if ($tabla['TABLE_NAME'] !== 'migrations' && $pdo->query('SELECT EXISTS(SELECT 1 FROM `'.$tabla['TABLE_NAME'].'` LIMIT 1)')->fetchColumn()) throw new RuntimeException('Una tabla de aplicación no quedó vacía.');
            }
            if (file_put_contents($marca,json_encode(['base'=>AislamientoCajaDemo::BASE,'puerto'=>3308,'estado'=>'COMPLETADA','fecha_utc'=>gmdate('c')],JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('Esquema aplicado, pero no se confirmó la marca final. No reejecutar; revisar con migrate:status del runtime.');
            }
        } finally {
            $liberar=$pdo->prepare('SELECT RELEASE_LOCK(?)');$liberar->execute([$nombre]);
        }
    }
}
