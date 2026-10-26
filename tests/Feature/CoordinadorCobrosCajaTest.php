<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Caja\{CoordinadorCobrosCaja, FalloCoordinacionCaja};
use Illuminate\Database\{Connection, ConnectionResolverInterface, MySqlConnection};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PDO;
use PDOException;
use PDOStatement;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

/** Prueba del protocolo con PDO simulado: nunca abre una conexión MySQL real. */
class CoordinadorCobrosCajaTest extends TestCase
{
    private function sesion(?object $servidor = null, int $id = 7): array
    {
        $servidor ??= (object) ['propietario' => null];
        $estado = (object) [
            'sql' => [], 'transaccion' => false, 'persistente' => false,
            'adquisicion' => 1, 'liberacion' => 1, 'perderAdquisicion' => false,
            'fallarRollback' => false, 'fallarCommit' => false, 'commitAplicadoAntesDeFallo' => false, 'id' => $id,
        ];
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()
            ->onlyMethods(['getAttribute', 'query', 'prepare', 'inTransaction', 'beginTransaction', 'commit', 'rollBack'])->getMock();
        $pdo->expects($this->atLeastOnce())->method('getAttribute')->with(PDO::ATTR_PERSISTENT)
            ->willReturnCallback(fn () => $estado->persistente);
        $pdo->method('inTransaction')->willReturnCallback(fn () => $estado->transaccion);
        $pdo->method('beginTransaction')->willReturnCallback(function () use ($estado) {
            $estado->sql[] = 'BEGIN';
            $estado->transaccion = true;
            return true;
        });
        $pdo->method('commit')->willReturnCallback(function () use ($estado) {
            $estado->sql[] = 'COMMIT';
            if ($estado->fallarCommit) {
                if ($estado->commitAplicadoAntesDeFallo) $estado->transaccion = false;
                throw new PDOException('Lost connection to server during query');
            }
            $estado->transaccion = false;
            return true;
        });
        $pdo->method('rollBack')->willReturnCallback(function () use ($estado) {
            $estado->sql[] = 'ROLLBACK';
            if ($estado->fallarRollback) {
                throw new PDOException('Lost connection to server during query');
            }
            $estado->transaccion = false;
            return true;
        });
        $pdo->method('query')->willReturnCallback(function (string $sql) use ($estado) {
            $estado->sql[] = $sql;
            $consulta = $this->createStub(PDOStatement::class);
            $consulta->method('fetch')->willReturn(['base_actual' => 'cafeteria_pro_pruebas', 'sesion' => $estado->id]);
            return $consulta;
        });
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($estado, $servidor) {
            $consulta = $this->createStub(PDOStatement::class);
            $consulta->method('execute')->willReturnCallback(function (array $parametros) use ($sql, $estado) {
                $estado->sql[] = $sql;
                $this->assertSame(CoordinadorCobrosCaja::nombreBloqueo('cafeteria_pro_pruebas'), $parametros[0]);
                return true;
            });
            $consulta->method('fetchColumn')->willReturnCallback(function () use ($sql, $estado, $servidor) {
                if (str_contains($sql, 'IS_USED_LOCK')) {
                    return $servidor->propietario;
                }
                if (str_contains($sql, 'GET_LOCK')) {
                    if ($servidor->propietario !== null && $servidor->propietario !== $estado->id) {
                        return 0;
                    }
                    if ($estado->adquisicion === 1) {
                        $servidor->propietario = $estado->id;
                    }
                    if ($estado->perderAdquisicion) {
                        throw new PDOException('Lost connection to server during query');
                    }
                    return $estado->adquisicion;
                }
                if ($estado->liberacion === 1) {
                    $servidor->propietario = null;
                }
                return $estado->liberacion;
            });
            return $consulta;
        });
        $conexion = new MySqlConnection($pdo, 'cafeteria_pro_pruebas', '', [
            'driver' => 'mysql', 'name' => 'mysql', 'options' => [],
        ]);
        $reconector = static fn () => throw new RuntimeException('Reconector original');
        $conexion->setReconnector($reconector);

        return [$conexion, $estado, $servidor, $reconector];
    }

    private function usar(Connection $conexion): void
    {
        DB::swap(new class($conexion) implements ConnectionResolverInterface {
            public function __construct(private Connection $conexion) {}
            public function connection($name = null): Connection { return $this->conexion; }
            public function getDefaultConnection() { return 'mysql'; }
            public function setDefaultConnection($name) {}
        });
    }

    public function test_protocolo_mantiene_lock_hasta_commit_y_restaura_reconector(): void
    {
        [$conexion, $estado, $servidor, $reconector] = $this->sesion();
        $this->usar($conexion);
        $resultado = (new CoordinadorCobrosCaja)->exclusivo(function () use ($conexion, $estado, $servidor) {
            $this->assertSame(7, $servidor->propietario);
            return $conexion->transaction(function () use ($estado) {
                $estado->sql[] = 'OPERACION';
                return 'correcto';
            }, 1);
        });
        $this->assertSame('correcto', $resultado);
        $this->assertNull($servidor->propietario);
        $this->assertLessThan(array_search('SELECT RELEASE_LOCK(?)', $estado->sql), array_search('COMMIT', $estado->sql));
        $this->assertSame($reconector, (new ReflectionProperty(Connection::class, 'reconnector'))->getValue($conexion));
    }

    public function test_timeout_no_ejecuta_operacion_ni_libera_lock_ajeno(): void
    {
        [$conexion, $estado] = $this->sesion();
        $estado->adquisicion = 0;
        $this->usar($conexion);
        try {
            (new CoordinadorCobrosCaja(0))->exclusivo(fn () => $this->fail('No debe ejecutar la operación.'));
            $this->fail('Debe informar timeout.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('caja', $e->errors());
        }
        $this->assertNotContains('SELECT RELEASE_LOCK(?)', $estado->sql);
        $this->assertNotNull($conexion->getRawPdo());
    }

    public function test_null_de_adquisicion_descarta_la_sesion_sin_operar(): void
    {
        [$conexion, $estado] = $this->sesion();
        $estado->adquisicion = null;
        $this->usar($conexion);
        try {
            (new CoordinadorCobrosCaja)->exclusivo(fn () => $this->fail('No debe operar.'));
            $this->fail('Debe rechazar adquisición no confirmada.');
        } catch (FalloCoordinacionCaja $e) {
            $this->assertFalse($e->resultadoIncierto);
        }
        $this->assertNull($conexion->getRawPdo());
    }

    public function test_excepcion_revierte_antes_de_liberar_y_no_repite_callback(): void
    {
        [$conexion, $estado, $servidor] = $this->sesion();
        $this->usar($conexion);
        $veces = 0;
        try {
            (new CoordinadorCobrosCaja)->exclusivo(function () use ($conexion, &$veces) {
                return $conexion->transaction(function () use (&$veces) {
                    $veces++;
                    throw new RuntimeException('Fallo esperado');
                }, 1);
            });
            $this->fail('Debe propagar la excepción.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo esperado', $e->getMessage());
        }
        $this->assertSame(1, $veces);
        $this->assertNull($servidor->propietario);
        $this->assertLessThan(array_search('SELECT RELEASE_LOCK(?)', $estado->sql), array_search('ROLLBACK', $estado->sql));
    }

    public function test_reconexion_esta_prohibida_mientras_se_posee_el_lock(): void
    {
        [$conexion, , $servidor, $original] = $this->sesion();
        $this->usar($conexion);
        try {
            (new CoordinadorCobrosCaja)->exclusivo(fn () => $conexion->reconnect());
            $this->fail('No debe reconectar sin adquirir de nuevo el lock.');
        } catch (FalloCoordinacionCaja $e) {
            $this->assertStringContainsString('no se permite reconectar', $e->getMessage());
        }
        $this->assertNull($servidor->propietario);
        $this->assertSame($original, (new ReflectionProperty(Connection::class, 'reconnector'))->getValue($conexion));
    }

    public function test_fallo_de_liberacion_reporta_resultado_incierto_y_descarta_pdo(): void
    {
        foreach ([0, null] as $resultado) {
            [$conexion, $estado] = $this->sesion();
            $estado->liberacion = $resultado;
            $this->usar($conexion);
            try {
                (new CoordinadorCobrosCaja)->exclusivo(fn () => $conexion->transaction(fn () => 'commit', 1));
                $this->fail('No debe devolver éxito sin comprobar liberación.');
            } catch (FalloCoordinacionCaja $e) {
                $this->assertTrue($e->resultadoIncierto);
            }
            $this->assertNull($conexion->getRawPdo());
        }
    }

    public function test_perdida_durante_get_lock_limpia_la_sesion_sin_repetir(): void
    {
        [$conexion, $estado, $servidor] = $this->sesion();
        $estado->perderAdquisicion = true;
        $this->usar($conexion);
        try {
            (new CoordinadorCobrosCaja)->exclusivo(fn () => $this->fail('No debe operar.'));
            $this->fail('Debe propagar adquisición incierta.');
        } catch (FalloCoordinacionCaja $e) {
            $this->assertFalse($e->resultadoIncierto);
            $this->assertNull($conexion->getRawPdo());
        }
        $this->assertNull($servidor->propietario);
        $this->assertSame(1, count(array_filter($estado->sql, fn ($s) => $s === 'SELECT GET_LOCK(?, ?)')));
    }

    public function test_pdo_persistente_se_rechaza_antes_de_adquirir(): void
    {
        [$conexion, $estado] = $this->sesion();
        $estado->persistente = true;
        $this->usar($conexion);
        try {
            (new CoordinadorCobrosCaja)->exclusivo(fn () => $this->fail('No debe operar con PDO persistente.'));
            $this->fail('Debe rechazar conexión persistente.');
        } catch (FalloCoordinacionCaja $e) {
            $this->assertStringContainsString('persistentes', $e->getMessage());
        }
        $this->assertNotContains('SELECT GET_LOCK(?, ?)', $estado->sql);
    }

    public function test_lock_previo_de_la_misma_sesion_no_se_adquiere_recursivamente(): void
    {
        [$conexion, $estado, $servidor] = $this->sesion();
        $servidor->propietario = 7;
        $this->usar($conexion);
        try {
            (new CoordinadorCobrosCaja)->exclusivo(fn () => $this->fail('No debe operar con bloqueo heredado.'));
            $this->fail('Debe rechazar recursividad.');
        } catch (FalloCoordinacionCaja) {
            $this->assertNull($conexion->getRawPdo());
        }
        $this->assertNotContains('SELECT GET_LOCK(?, ?)', $estado->sql);
    }

    public function test_rollback_incierto_no_libera_prematuramente_y_descarta_sesion(): void
    {
        [$conexion, $estado] = $this->sesion();
        $this->usar($conexion);
        try {
            (new CoordinadorCobrosCaja)->exclusivo(function () use ($conexion, $estado) {
                $conexion->beginTransaction();
                $estado->fallarRollback = true;
                throw new RuntimeException('Fallo de operación');
            });
            $this->fail('Debe descartar sesión con rollback no confirmado.');
        } catch (FalloCoordinacionCaja $e) {
            $this->assertTrue($e->resultadoIncierto);
        }
        $this->assertNull($conexion->getRawPdo());
        $this->assertNotContains('SELECT RELEASE_LOCK(?)', $estado->sql);
    }

    public function test_dos_sesiones_simuladas_compiten_por_el_mismo_nombre(): void
    {
        $servidor = (object) ['propietario' => null];
        [$primera] = $this->sesion($servidor, 7);
        [$segunda] = $this->sesion($servidor, 8);
        $seleccionada = $primera;
        // La variable seleccionada debe leerse por referencia al cambiar de cliente simulado.
        DB::swap(new class($seleccionada) implements ConnectionResolverInterface {
            public function __construct(public Connection &$seleccionada) {}
            public function connection($name = null): Connection { return $this->seleccionada; }
            public function getDefaultConnection() { return 'mysql'; }
            public function setDefaultConnection($name) {}
        });
        $orden = [];
        (new CoordinadorCobrosCaja(0))->exclusivo(function () use (&$seleccionada, $primera, $segunda, &$orden) {
            $orden[] = 'primera';
            $seleccionada = $segunda;
            try {
                (new CoordinadorCobrosCaja(0))->exclusivo(fn () => $this->fail('No puede entrar mientras la primera posee el lock.'));
                $this->fail('Debe esperar o agotar timeout.');
            } catch (ValidationException) {
                $orden[] = 'segunda_bloqueada';
            } finally {
                $seleccionada = $primera;
            }
        });
        $seleccionada = $segunda;
        (new CoordinadorCobrosCaja(0))->exclusivo(function () use (&$orden) { $orden[] = 'segunda'; });
        $this->assertSame(['primera', 'segunda_bloqueada', 'segunda'], $orden);
        $this->assertNull($servidor->propietario);
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
    }

    public function test_commit_con_respuesta_perdida_no_repite_y_declara_resultado_incierto(): void
    {
        [$conexion, $estado] = $this->sesion();
        $estado->fallarCommit = true;
        $this->usar($conexion);
        $veces = 0;
        try {
            (new CoordinadorCobrosCaja)->exclusivo(function () use ($conexion, &$veces) {
                return $conexion->transaction(function () use (&$veces) { return ++$veces; }, 1);
            });
            $this->fail('No debe presentar el commit incierto como éxito.');
        } catch (FalloCoordinacionCaja $e) {
            $this->assertTrue($e->resultadoIncierto);
        }
        $this->assertSame(1, $veces);
        $this->assertSame(1, count(array_filter($estado->sql, fn ($s) => $s === 'COMMIT')));
        $this->assertNull($conexion->getRawPdo());
    }

    public function test_transaccion_pdo_ajena_se_rechaza_sin_revertirla(): void
    {
        [$conexion, $estado] = $this->sesion();
        $estado->transaccion = true;
        $this->usar($conexion);
        try {
            (new CoordinadorCobrosCaja)->exclusivo(fn () => $this->fail('No debe operar dentro de la transacción ajena.'));
            $this->fail('Debe rechazar la transacción existente.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('fuera de Laravel', $e->getMessage());
        }
        $this->assertTrue($estado->transaccion);
        $this->assertNotContains('ROLLBACK', $estado->sql);
        $this->assertNotContains('SELECT GET_LOCK(?, ?)', $estado->sql);
    }

    public function test_commit_aplicado_o_no_aplicado_con_acuse_perdido_siempre_es_incierto_sin_reconexion(): void
    {
        foreach ([false,true] as $aplicado) {
            [$conexion,$estado]=$this->sesion();
            $estado->fallarCommit=true; $estado->commitAplicadoAntesDeFallo=$aplicado;
            $this->usar($conexion); $llamadas=0;
            try {
                (new CoordinadorCobrosCaja)->exclusivo(function () use ($conexion, &$llamadas) {
                    return $conexion->transaction(function () use (&$llamadas) { return ++$llamadas; },1);
                });
                $this->fail('No confirmar resultado a partir de un acuse perdido.');
            } catch (FalloCoordinacionCaja $e) { $this->assertTrue($e->resultadoIncierto); }
            $this->assertSame(1,$llamadas);
            $this->assertSame(1,count(array_filter($estado->sql,fn($s)=>$s === 'COMMIT')));
            $this->assertNull($conexion->getRawPdo());
            $this->assertSame(!$aplicado,in_array('ROLLBACK',$estado->sql,true));
        }
    }
}
