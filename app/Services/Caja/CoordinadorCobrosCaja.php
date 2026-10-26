<?php

declare(strict_types=1);

namespace App\Services\Caja;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PDO;
use ReflectionProperty;
use Throwable;
use WeakMap;

final class CoordinadorCobrosCaja implements CoordinacionCobrosCaja
{
    use DetectsLostConnections;

    private static ?WeakMap $activas = null;

    public static function resolver(): CoordinacionCobrosCaja
    {
        return app()->bound(CoordinacionCobrosCaja::class)
            ? app(CoordinacionCobrosCaja::class) : new self;
    }

    public function __construct(private readonly int $esperaSegundos = 5)
    {
        if ($esperaSegundos < 0 || $esperaSegundos > 30) {
            throw new LogicException('El timeout de caja debe estar entre 0 y 30 segundos.');
        }
    }

    public function permiteCierreDefinitivo(): bool
    {
        // No habilitar por configuración: falta la prueba real cobro/cierre en MariaDB.
        return false;
    }

    public static function nombreBloqueo(string $base): string
    {
        // No depende del día, conexión Laravel o alias del host. Menos de 64 caracteres.
        return 'karma:caja:'.substr(hash('sha256', strtolower($base)), 0, 48);
    }

    public function exclusivo(Closure $operacion): mixed
    {
        $conexion = DB::connection();
        self::$activas ??= new WeakMap;
        if (isset(self::$activas[$conexion]) || $conexion->transactionLevel() !== 0) {
            throw new LogicException('No se permite coordinación reentrante ni adquirirla dentro de una transacción.');
        }
        if ($conexion->getDriverName() === 'sqlite') {
            return $this->soloPruebasSqlite($conexion, $operacion);
        }
        if (!in_array($conexion->getDriverName(), ['mysql', 'mariadb'], true)
            || $conexion->getConfig('read') || $conexion->getConfig('write') || $conexion->hasDirectConnection()) {
            throw new FalloCoordinacionCaja('Caja requiere una única conexión MariaDB/MySQL sin separación de lectura/escritura.');
        }
        if (($conexion->getConfig('options')[PDO::ATTR_PERSISTENT] ?? false)) {
            throw new FalloCoordinacionCaja('Caja no permite conexiones PDO persistentes.');
        }

        // Laravel puede reconectar al iniciar una transacción o repetir consultas tras perder sesión.
        // No tiene getter público para el callback; conservar/restaurar exactamente el original.
        $propiedad = new ReflectionProperty(Connection::class, 'reconnector');
        $reconector = $propiedad->getValue($conexion);
        $conexion->setReconnector(static function () {
            throw new FalloCoordinacionCaja('Se perdió la sesión coordinada; no se permite reconectar ni repetir la operación.', true);
        });
        self::$activas[$conexion] = true;
        $pdo = null;
        $nombre = null;
        $adquirido = $intentoIncierto = $descartar = $iniciada = false;
        $rollbackSeguro = true;
        $error = null;
        $resultado = null;
        try {
            $pdo = $conexion->getPdo();
            if ($pdo->getAttribute(PDO::ATTR_PERSISTENT)) {
                throw new FalloCoordinacionCaja('Caja no permite conexiones PDO persistentes.');
            }
            if ($pdo->inTransaction()) {
                throw new LogicException('La sesión ya tiene una transacción iniciada fuera de Laravel.');
            }
            $meta = $pdo->query('SELECT DATABASE() AS base_actual, CONNECTION_ID() AS sesion')->fetch(PDO::FETCH_ASSOC);
            if (!$meta || !$meta['base_actual'] || $meta['base_actual'] !== $conexion->getDatabaseName()) {
                throw new FalloCoordinacionCaja('La base efectiva no coincide con la conexión de caja.');
            }
            $nombre = self::nombreBloqueo($meta['base_actual']);
            $propietario = $this->escalar($pdo, 'SELECT IS_USED_LOCK(?)', [$nombre]);
            if ($propietario !== null && (string) $propietario === (string) $meta['sesion']) {
                $descartar = true;
                throw new FalloCoordinacionCaja('La sesión conserva un bloqueo previo; no se permite adquirirlo recursivamente.');
            }
            $intentoIncierto = true;
            $obtenido = $this->escalar($pdo, 'SELECT GET_LOCK(?, ?)', [$nombre, $this->esperaSegundos]);
            $intentoIncierto = false;
            if ($obtenido === 0 || $obtenido === '0') {
                throw ValidationException::withMessages(['caja' => 'Caja ocupada: terminó el tiempo de espera sin registrar la operación.']);
            }
            if ($obtenido !== 1 && $obtenido !== '1') {
                $descartar = true;
                throw new FalloCoordinacionCaja('MariaDB no confirmó la adquisición del bloqueo de caja.');
            }
            $adquirido = true;
            $iniciada = true;
            // El callback contiene una única transacción con attempts=1, hasta su commit/rollback.
            $resultado = $operacion();
            if ($conexion->getRawPdo() !== $pdo || $conexion->transactionLevel() !== 0 || $pdo->inTransaction()) {
                $descartar = true;
                throw new FalloCoordinacionCaja('La operación cambió de sesión o dejó una transacción abierta.', true);
            }
            if ((string) $this->escalar($pdo, 'SELECT IS_USED_LOCK(?)', [$nombre]) !== (string) $meta['sesion']) {
                $descartar = true;
                throw new FalloCoordinacionCaja('No se pudo verificar la propiedad del bloqueo al terminar.', true);
            }
        } catch (Throwable $e) {
            if ($this->causedByLostConnection($e) || ($e instanceof FalloCoordinacionCaja && $e->resultadoIncierto)) {
                $descartar = true;
                $error = new FalloCoordinacionCaja(
                    'Se perdió la sesión coordinada; no se permite reconectar ni reintentar automáticamente. Consulta el estado de la operación.',
                    $iniciada, $e,
                );
            } else {
                $error = $e;
            }
            if ($intentoIncierto) {
                $descartar = true;
            }
        } finally {
            try {
                // Nunca liberar antes del rollback si el callback dejó una transacción abierta.
                if ($iniciada) {
                    if ($conexion->transactionLevel() > 0) {
                        $conexion->rollBack(0);
                    }
                    if ($pdo && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                }
            } catch (Throwable $e) {
                $descartar = true;
                $rollbackSeguro = false;
                $error = new FalloCoordinacionCaja('No se confirmó el rollback; se descarta la sesión sin reutilizarla.', $iniciada, $error ?? $e);
            }
            try {
                if ($rollbackSeguro && ($adquirido || $intentoIncierto) && $pdo && !$pdo->inTransaction()) {
                    $liberado = $this->escalar($pdo, 'SELECT RELEASE_LOCK(?)', [$nombre]);
                    if ($liberado !== 1 && $liberado !== '1') {
                        throw new FalloCoordinacionCaja('MariaDB no confirmó la liberación del bloqueo de caja.');
                    }
                }
            } catch (Throwable $e) {
                $descartar = true;
                $error = new FalloCoordinacionCaja(
                    'Falló la liberación del bloqueo. Consulta el estado de la operación antes de reintentar.',
                    $iniciada, $error ?? $e,
                );
            }
            try {
                if ($descartar) {
                    // Solo PDO no persistente: al cerrar la sesión se revierten transacciones y liberan locks.
                    $conexion->disconnect();
                    $pdo = null;
                }
            } finally {
                $propiedad->setValue($conexion, $reconector);
                unset(self::$activas[$conexion]);
            }
        }
        if ($error) {
            throw $error;
        }

        return $resultado;
    }

    private function escalar(PDO $pdo, string $sql, array $parametros): mixed
    {
        // PDO original: estas consultas nunca pasan por el mecanismo de reconexión de Laravel.
        $consulta = $pdo->prepare($sql);
        $consulta->execute($parametros);

        return $consulta->fetchColumn();
    }

    private function soloPruebasSqlite(Connection $conexion, Closure $operacion): mixed
    {
        if (!app()->runningUnitTests() || $conexion->getDatabaseName() !== ':memory:') {
            throw new FalloCoordinacionCaja('SQLite solo se admite en pruebas con base en memoria.');
        }
        foreach ($conexion->select('PRAGMA database_list') as $base) {
            if ($base->file !== '') {
                throw new FalloCoordinacionCaja('No se admiten bases SQLite persistentes.');
            }
        }
        if ($conexion->getPdo()->inTransaction()) {
            throw new LogicException('La sesión SQLite ya tiene una transacción fuera de Laravel.');
        }
        self::$activas[$conexion] = true;
        try {
            $resultado = $operacion();
            if ($conexion->transactionLevel() !== 0 || $conexion->getPdo()->inTransaction()) {
                throw new LogicException('El callback dejó una transacción abierta.');
            }

            return $resultado;
        } finally {
            try {
                if ($conexion->transactionLevel() > 0) {
                    $conexion->rollBack(0);
                }
                if ($conexion->getPdo()->inTransaction()) {
                    $conexion->getPdo()->rollBack();
                }
            } finally {
                unset(self::$activas[$conexion]);
            }
        }
    }
}
