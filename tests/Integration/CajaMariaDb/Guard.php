<?php

declare(strict_types=1);

namespace Tests\Integration\CajaMariaDb;

use PDO;
use RuntimeException;

/** No tiene efectos al cargarse. No utiliza credenciales ni conexiones de .env. */
final class Guard
{
    public const BASE = 'cafeteria_caja_integracion';
    public const MARCA = 'KARMA_CAJA_INTEGRACION_V1';

    public static function validarNombre(string $nombre): void
    {
        if ($nombre !== self::BASE) {
            throw new RuntimeException('STOP: solo se admite la base desechable exacta cafeteria_caja_integracion.');
        }
    }

    public static function huella(): string
    {
        $root = dirname(__DIR__, 3);
        $archivos = array_merge([$root.'/app/Http/Controllers/VentaController.php',
            $root.'/app/Models/User.php', $root.'/app/Models/Rol.php', $root.'/app/Models/Permiso.php',
            $root.'/app/Http/Middleware/VerificarPermiso.php', $root.'/database/seeders/PermisoSeeder.php',
            $root.'/app/Models/Bitacora.php', $root.'/app/Models/CierreCaja.php',
            $root.'/app/Models/CierreCajaVersion.php', $root.'/app/Models/CierreCajaVersionVenta.php',
            $root.'/app/Models/Builders/HistorialFinancieroBuilder.php',
            $root.'/app/Models/Concerns/HistorialFinanciero.php'],
            glob($root.'/app/Services/Caja/*.php'), glob(__DIR__.'/*.php'), glob($root.'/database/migrations/*.php'));
        sort($archivos);

        return hash('sha256', implode('', array_map(static fn ($p) => substr($p, strlen($root)).hash_file('sha256', $p), $archivos)));
    }

    public static function verificar(PDO $pdo): array
    {
        $meta = $pdo->query('SELECT DATABASE() AS base_actual, VERSION() AS version_servidor, CONNECTION_ID() AS sesion')->fetch(PDO::FETCH_ASSOC);
        self::validarNombre((string) ($meta['base_actual'] ?? ''));
        if (!preg_match('/(?:^|-)10\.4\.32-MariaDB(?:-|$)/', (string) $meta['version_servidor'])) {
            throw new RuntimeException('STOP: esta certificación exige MariaDB 10.4.32.');
        }

        return $meta;
    }

    public static function boot(): array
    {
        $root = dirname(__DIR__, 3);
        if (getenv('KARMA_CAJA_ALLOW') !== self::BASE) {
            throw new RuntimeException('STOP: falta la aceptación explícita KARMA_CAJA_ALLOW.');
        }
        self::validarNombre((string) getenv('KARMA_CAJA_DB'));
        $host = (string) getenv('KARMA_CAJA_HOST');
        $puerto = (string) getenv('KARMA_CAJA_PORT');
        $usuario = (string) getenv('KARMA_CAJA_USER');
        $clave = getenv('KARMA_CAJA_PASSWORD');
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || !ctype_digit($puerto) || (int) $puerto < 1 || (int) $puerto > 65535
            || $usuario !== 'karma_caja_it' || $clave === false || $clave === '') {
            throw new RuntimeException('STOP: se requiere conexión local explícita y la cuenta restringida karma_caja_it con contraseña.');
        }
        if (PHP_VERSION_ID < 80300 || !extension_loaded('pdo_mysql') || !function_exists('proc_open')) {
            throw new RuntimeException('STOP: se requiere PHP 8.3+, pdo_mysql y proc_open.');
        }
        $cacheInexistente = __DIR__.'/.config-cache-prohibida.php';
        if (is_file($root.'/bootstrap/cache/config.php') || file_exists($cacheInexistente)) {
            throw new RuntimeException('STOP: hay configuración cacheada; revisar manualmente sin utilizar la caché de producción.');
        }
        // Primera conexión: DSN cerrado a la base permitida; no hay conexión administrativa.
        $pdo = new PDO('mysql:host='.$host.';port='.$puerto.';dbname='.self::BASE.';charset=utf8mb4', $usuario, $clave, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $meta = self::verificar($pdo); // Consulta real ANTES de cargar Laravel y antes de escribir.
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        foreach ([
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_CONFIG_CACHE' => $cacheInexistente,
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION' => 'caja_integracion', 'DB_DATABASE' => self::BASE,
            'DB_HOST' => $host, 'DB_PORT' => $puerto, 'DB_USERNAME' => $usuario, 'DB_PASSWORD' => $clave,
            'DATABASE_URL' => '', 'DB_URL' => '', 'CACHE_DRIVER' => 'array', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        require_once $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $config = ['driver' => 'mysql', 'name' => 'caja_integracion', 'database' => self::BASE,
            'host' => $host, 'port' => $puerto, 'username' => $usuario, 'password' => $clave,
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
            'strict' => true, 'engine' => 'InnoDB', 'options' => [PDO::ATTR_PERSISTENT => false]];
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) use ($config) {
            $app['config']->set('database.default', 'caja_integracion');
            $app['config']->set('database.connections', ['caja_integracion' => $config]);
            $app['config']->set('app.timezone', 'America/La_Paz');
        });
        $conexion = new \Illuminate\Database\MySqlConnection($pdo, self::BASE, '', $config);
        $conexion->beforeExecuting(static function (string $sql, array $bindings, $c) {
            if (preg_match('/\b(?:cafeteria_pro|cafeteria_pro_pruebas)\b/i', $sql)
                || preg_match('/^\s*(?:DROP|TRUNCATE|USE)\b/i', $sql)) {
                throw new RuntimeException('STOP: SQL prohibido por el arnés de integración.');
            }
            $actual = $c->getRawPdo();
            if (!$actual instanceof PDO) {
                throw new RuntimeException('STOP: sesión no verificada; no se permite reconectar.');
            }
            self::verificar($actual); // Antes de CADA sentencia, incluidas todas las escrituras y DDL.
        });
        $app->beforeBootstrapping(\Illuminate\Foundation\Bootstrap\BootProviders::class, function ($app) use ($conexion) {
            $app['db']->extend('caja_integracion', static fn () => $conexion);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $conexion = $app['db']->connection();
        $conexion->setReconnector(static fn () => throw new RuntimeException('STOP: no se permite reconectar durante estas pruebas.'));
        self::verificar($conexion->getPdo());

        return [$app, $conexion, $meta];
    }

    public static function exigirPreparada(): void
    {
        $fila = \Illuminate\Support\Facades\DB::table('caja_it_control')->where('id', 1)->first();
        if (!$fila || $fila->marca !== self::MARCA) {
            throw new RuntimeException('STOP: falta la marca de preparación exclusiva de integración.');
        }
    }

    /** Guarda adicional para fixtures nuevos; SQLite se admite solo dentro de PHPUnit CLI. */
    public static function autorizarFixtures(): void
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('STOP: fixtures solo disponibles en CLI.');
        $conexion = \Illuminate\Support\Facades\DB::connection();
        if ($conexion->getDriverName() === 'mysql') {
            self::validarNombre($conexion->getDatabaseName()); // Antes de consultar una sesión equivocada.
            if (getenv('KARMA_CAJA_ALLOW') !== self::BASE || $conexion->getConfig('username') !== 'karma_caja_it') {
                throw new RuntimeException('STOP: falta consentimiento o cuenta restringida para fixtures.');
            }
            $pdo = $conexion->getRawPdo();
            if (!$pdo instanceof PDO) throw new RuntimeException('STOP: sesión no verificada.');
            self::verificar($pdo);
        } elseif ($conexion->getDriverName() === 'sqlite' && PHP_SAPI === 'cli'
            && defined('PHPUNIT_COMPOSER_INSTALL') && app()->environment('testing')
            && config('database.default') === 'sqlite' && $conexion->getDatabaseName() === ':memory:'
            && config('database.connections.sqlite.database') === ':memory:'
            && !config('database.connections.sqlite.url')) {
            foreach ($conexion->select('PRAGMA database_list') as $base) {
                if ($base->file !== '') throw new RuntimeException('STOP: SQLite debe ser exclusivamente memoria.');
            }
        } else throw new RuntimeException('STOP: fixtures fuera del entorno aislado.');
        self::exigirPreparada();
    }
}
