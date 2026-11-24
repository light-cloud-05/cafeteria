<?php

declare(strict_types=1);

namespace App\Services\Caja;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

final class AislamientoCajaDemo
{
    public const BASE = 'cafeteria_defensa_demo';
    public const CUENTA = 'karma_defensa_demo';
    private ?PDO $sesion = null;

    public static function solicitada(): bool
    {
        return app()->environment('demostracion') || config('demostracion.activa') === true;
    }

    public function verificar(): void
    {
        $conexion = DB::connection();
        self::validarConfiguracion(app()->environment(), config('demostracion.activa'),
            $conexion->getConfig(), (string) config('demostracion.servidor'));
        if ($conexion->hasDirectConnection()) self::rechazar();
        $pdo = $conexion->getPdo();
        if (($this->sesion !== null && $this->sesion !== $pdo)
            || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || $pdo->getAttribute(PDO::ATTR_PERSISTENT)
            || $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) !== '127.0.0.1 via TCP/IP') self::rechazar();
        $meta = $pdo->query('SELECT DATABASE() AS base, CURRENT_USER() AS cuenta, USER() AS usuario, @@hostname AS servidor, @@port AS puerto, CURRENT_ROLE() AS rol')->fetch(PDO::FETCH_ASSOC);
        self::validarIdentidad($meta ?: [], (string) config('demostracion.servidor'), (int) $conexion->getConfig('port'));
        self::validarGrants($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
        $this->sesion = $pdo;
    }

    public function vigilar(): void
    {
        $this->verificar();
        DB::connection()->beforeExecuting(function (string $sql, array $bindings, Connection $conexion): void {
            // No se permite reconectar ni cambiar de base entre la guarda y la escritura.
            if ($conexion->getRawPdo() !== $this->sesion
                || preg_match('/\b(?:cafeteria_pro|cafeteria_pro_pruebas)\b|^\s*(?:USE|GRANT|REVOKE|SET\s+ROLE|DROP|TRUNCATE)\b/i', $sql)) self::rechazar();
            $this->verificar();
        });
        DB::connection()->setReconnector(static fn () => self::rechazar());
    }

    public static function validarConfiguracion(string $entorno, mixed $activa, array $config, string $servidor): void
    {
        if ($entorno !== 'demostracion' || $activa !== true || $servidor === ''
            || ($config['driver'] ?? '') !== 'mysql'
            || ($config['database'] ?? '') !== self::BASE
            || ($config['username'] ?? '') !== self::CUENTA
            || ($config['host'] ?? '') !== '127.0.0.1'
            || !ctype_digit((string) ($config['port'] ?? '')) || (int) $config['port'] < 1 || (int) $config['port'] > 65535
            || empty($config['password']) || !empty($config['url']) || !empty($config['unix_socket'])
            || !empty($config['read']) || !empty($config['write']) || !empty($config['prefix'])
            // No permitir INIT_COMMAND ni otras opciones que actúen antes de la guarda.
            || !empty($config['options'])) self::rechazar();
    }

    public static function validarIdentidad(array $meta, string $servidor, int $puerto): void
    {
        if (($meta['base'] ?? '') !== self::BASE
            || !in_array($meta['cuenta'] ?? '', [self::CUENTA.'@localhost', self::CUENTA.'@127.0.0.1'], true)
            || !in_array($meta['usuario'] ?? '', [self::CUENTA.'@localhost', self::CUENTA.'@127.0.0.1'], true)
            || ($meta['servidor'] ?? '') !== $servidor || (int) ($meta['puerto'] ?? 0) !== $puerto
            || !array_key_exists('rol', $meta) || !in_array($meta['rol'], ['NONE', null], true)) self::rechazar();
    }

    public static function validarGrants(array $grants): void
    {
        $privilegios = [];
        if (!$grants) self::rechazar();
        foreach ($grants as $grant) {
            // Gramática deliberadamente cerrada: rechaza roles, PROXY, GRANT OPTION,
            // privilegios globales, comodines sin escapar y cualquier otra base.
            if (!preg_match('/\AGRANT (.+?) ON (.+?) TO [`\x27]karma_defensa_demo[`\x27]@[`\x27](?:localhost|127\.0\.0\.1)[`\x27](.*)\z/i', $grant, $m)) self::rechazar();
            if ($m[1] === 'USAGE' && $m[2] === '*.*') {
                // MariaDB puede añadir el hash de autenticación a USAGE. No se registra.
                if ($m[3] !== '' && !preg_match('/\A IDENTIFIED BY PASSWORD \x27[^\x27]*\x27\z/', $m[3])) self::rechazar();
                continue;
            }
            if ($m[2] !== '`cafeteria\\_defensa\\_demo`.*' || $m[3] !== '') self::rechazar();
            foreach (explode(', ', $m[1]) as $privilegio) {
                if (!in_array($privilegio, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'], true)) self::rechazar();
                $privilegios[$privilegio] = true;
            }
        }
        foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilegio) if (!isset($privilegios[$privilegio])) self::rechazar();
    }

    private static function rechazar(): never
    {
        throw new RuntimeException('Demostración rechazada: no se pudo acreditar el aislamiento de la base, conexión y cuenta. No continúes ni reintentes escrituras.');
    }
}
