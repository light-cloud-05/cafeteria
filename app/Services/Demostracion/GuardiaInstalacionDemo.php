<?php

declare(strict_types=1);

namespace App\Services\Demostracion;

use App\Services\Caja\AislamientoCajaDemo;
use PDO;
use App\Services\Demostracion\RechazoInstalacionDemo as RuntimeException;

final class GuardiaInstalacionDemo
{
    public const CUENTA = 'karma_demo_instalador';
    public const PUERTO = 3308;
    public const PRIVILEGIOS = ['SELECT','INSERT','UPDATE','DELETE','CREATE','ALTER','INDEX','REFERENCES','DROP',
        'SHOW VIEW','TRIGGER','EVENT','ALTER ROUTINE'];

    public static function configuracion(array $env): void
    {
        if (($env['APP_ENV'] ?? '') !== 'demostracion' || ($env['CAJA_DEMO_ACTIVA'] ?? '') !== 'true'
            || ($env['DB_CONNECTION'] ?? '') !== 'mysql' || ($env['DB_HOST'] ?? '') !== '127.0.0.1'
            || ($env['DB_PORT'] ?? '') !== (string)self::PUERTO || ($env['DB_DATABASE'] ?? '') !== AislamientoCajaDemo::BASE
            || ($env['DB_USERNAME'] ?? '') !== AislamientoCajaDemo::CUENTA || empty($env['CAJA_DEMO_SERVIDOR'])
            || !empty($env['DATABASE_URL']) || !empty($env['DB_URL']) || !empty($env['DB_SOCKET'])
            || !empty($env['MYSQL_ATTR_SSL_CA'])) {
            throw new RuntimeException('Configuración demo no admitida: no se abrió una conexión instaladora.');
        }
    }

    public static function identidad(array $meta, string $servidor): void
    {
        $cuentas = [self::CUENTA.'@localhost', self::CUENTA.'@127.0.0.1'];
        if (($meta['base'] ?? '') !== AislamientoCajaDemo::BASE || !in_array($meta['cuenta'] ?? '',$cuentas,true)
            || !in_array($meta['usuario'] ?? '',$cuentas,true) || ($meta['servidor'] ?? '') !== $servidor
            || (int)($meta['puerto'] ?? 0) !== self::PUERTO || !array_key_exists('rol',$meta)
            || !in_array($meta['rol'],[null,'NONE'],true) || !str_starts_with($meta['version'] ?? '', '10.4.32-MariaDB')
            || strtoupper($meta['motor'] ?? '') !== 'INNODB' || (int)($meta['fk'] ?? 0) !== 1
            || !preg_match('/(?:^|,)(?:STRICT_TRANS_TABLES|STRICT_ALL_TABLES)(?:,|$)/',$meta['modo'] ?? '')
            || !in_array('NO_ENGINE_SUBSTITUTION',explode(',',$meta['modo'] ?? ''),true)) {
            throw new RuntimeException('Identidad, versión, motor o configuración efectiva del servidor no admitidos.');
        }
    }

    public static function grants(array $grants): void
    {
        $obtenidos=[];
        $permitidos=[...self::PRIVILEGIOS,'CREATE TEMPORARY TABLES','LOCK TABLES','EXECUTE','CREATE VIEW','CREATE ROUTINE','DELETE HISTORY'];
        if (!$grants) throw new RuntimeException('No se acreditaron permisos instaladores.');
        foreach ($grants as $grant) {
            if (!preg_match('/\AGRANT (.+?) ON (.+?) TO [`\x27]karma_demo_instalador[`\x27]@[`\x27](?:localhost|127\.0\.0\.1)[`\x27](.*)\z/i',$grant,$m)) {
                throw new RuntimeException('Grants instaladores no admitidos; no se imprime su contenido.');
            }
            if ($m[1] === 'USAGE' && $m[2] === '*.*') {
                if ($m[3] !== '' && !preg_match('/\A IDENTIFIED BY PASSWORD \x27[^\x27]*\x27\z/',$m[3])) {
                    throw new RuntimeException('Atributos globales de la cuenta no admitidos.');
                }
                continue;
            }
            if ($m[2] !== '`cafeteria\\_defensa\\_demo`.*' || $m[3] !== '') {
                throw new RuntimeException('La cuenta instaladora debe estar limitada a la base literal demo, sin GRANT OPTION.');
            }
            $lista=$m[1] === 'ALL PRIVILEGES' ? $permitidos : explode(', ',$m[1]);
            foreach ($lista as $privilegio) {
                if (!in_array($privilegio,$permitidos,true)) throw new RuntimeException('Privilegio instalador inesperado.');
                $obtenidos[$privilegio]=true;
            }
        }
        foreach (self::PRIVILEGIOS as $privilegio) {
            if (!isset($obtenidos[$privilegio])) throw new RuntimeException('Falta un privilegio necesario para instalar o inspeccionar: '.$privilegio.'.');
        }
    }

    public static function vacia(array $inventario): void
    {
        foreach (['tablas_vistas','triggers','rutinas','eventos'] as $tipo) {
            if (!array_key_exists($tipo,$inventario) || !in_array($inventario[$tipo],[0,'0'],true)) {
                throw new RuntimeException('La base no está completamente vacía o no se acreditó su inventario. No se admite reanudar una instalación parcial.');
            }
        }
    }

    public static function inspeccionar(PDO $pdo, string $servidor, bool $exigirVacia = true): array
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $pdo->getAttribute(PDO::ATTR_PERSISTENT)
            || $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) !== '127.0.0.1 via TCP/IP' || $pdo->inTransaction()) {
            throw new RuntimeException('La instalación exige una sesión PDO TCP local, no persistente, sin transacción abierta.');
        }
        $meta=$pdo->query('SELECT DATABASE() AS base, CURRENT_USER() AS cuenta, USER() AS usuario, @@hostname AS servidor, @@port AS puerto, CURRENT_ROLE() AS rol, VERSION() AS version, @@default_storage_engine AS motor, @@foreign_key_checks AS fk, @@sql_mode AS modo')->fetch(PDO::FETCH_ASSOC);
        self::identidad($meta ?: [],$servidor);
        self::grants($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
        $inventario=[];
        foreach (['tablas_vistas'=>['TABLES','TABLE_SCHEMA'],'triggers'=>['TRIGGERS','TRIGGER_SCHEMA'],
            'rutinas'=>['ROUTINES','ROUTINE_SCHEMA'],'eventos'=>['EVENTS','EVENT_SCHEMA']] as $nombre=>[$tabla,$campo]) {
            $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.{$tabla} WHERE {$campo} = ?");
            $q->execute([AislamientoCajaDemo::BASE]);$inventario[$nombre]=(int)$q->fetchColumn();
        }
        if ($exigirVacia) self::vacia($inventario);
        return $inventario;
    }
}
