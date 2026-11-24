<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Caja\CoordinadorCobrosCaja;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Integration\CajaMariaDb\Guard;

require_once dirname(__DIR__).'/Integration/CajaMariaDb/Guard.php';

class CajaIntegracionGuardTest extends TestCase
{
    private array $entorno = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['EXPECTED_VERSION', 'ALLOW', 'DB', 'HOST', 'PORT', 'USER', 'PASSWORD'] as $nombre) {
            $key = 'KARMA_CAJA_'.$nombre;
            $this->entorno[$key] = getenv($key);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->entorno as $key => $valor) {
            putenv($valor === false ? $key : $key.'='.$valor);
        }
        parent::tearDown();
    }

    public function test_solo_admite_el_nombre_desechable_exacto(): void
    {
        Guard::validarNombre('cafeteria_caja_integracion');
        foreach (['cafeteria_pro', 'cafeteria_pro_pruebas', '', 'CAFETERIA_CAJA_INTEGRACION',
            'cafeteria_caja_integracion ', 'otra_base'] as $base) {
            try {
                Guard::validarNombre($base);
                $this->fail('Debe rechazar cualquier nombre distinto del autorizado.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('STOP', $e->getMessage());
            }
        }
    }

    public function test_verifica_la_base_y_version_mediante_pdo_sin_abrir_conexion(): void
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetch')->willReturn(['base_actual' => Guard::BASE, 'version_servidor' => '10.4.32-MariaDB', 'sesion' => 123, 'cuenta_autenticada' => 'karma_caja_it@localhost']);
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['query'])->getMock();
        $pdo->expects($this->once())->method('query')
            ->with('SELECT DATABASE() AS base_actual, VERSION() AS version_servidor, CONNECTION_ID() AS sesion, CURRENT_USER() AS cuenta_autenticada')
            ->willReturn($statement);
        $this->assertSame(Guard::BASE, Guard::verificar($pdo)['base_actual']);
    }

    public function test_consulta_de_base_incorrecta_detiene_la_operacion(): void
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetch')->willReturn(['base_actual' => 'cafeteria_pro_pruebas', 'version_servidor' => '10.4.32-MariaDB', 'sesion' => 123]);
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['query'])->getMock();
        $pdo->expects($this->once())->method('query')->willReturn($statement);
        $this->expectException(RuntimeException::class);
        Guard::verificar($pdo);
    }

    public function test_preparar_arnes_no_habilita_el_cierre_de_produccion(): void
    {
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', Guard::huella());
    }

    public function test_seleccion_explicita_y_default_compatibles(): void
    {
        $this->assertSame('10.4.32', Guard::versionEsperada());
        foreach (['10.4.32', '10.11.14'] as $version) {
            putenv('KARMA_CAJA_EXPECTED_VERSION='.$version);
            $this->assertSame($version, Guard::versionEsperada());
            foreach ([$version.'-MariaDB', '5.5.5-'.$version.'-MariaDB-0+deb12u1'] as $real) {
                foreach (['localhost', '127.0.0.1', '::1'] as $host) {
                    $meta = Guard::verificar($this->pdoSimulado($real, 'karma_caja_it@'.$host));
                    $this->assertSame($version, $meta['version_esperada']);
                    $this->assertSame($real, $meta['version_servidor']);
                    $this->assertArrayNotHasKey('cuenta_autenticada', $meta);
                }
            }
        }
    }

    public function test_rechaza_versiones_no_seleccionadas_y_otro_motor(): void
    {
        foreach (['10.4.32', '10.11.14'] as $esperada) {
            putenv('KARMA_CAJA_EXPECTED_VERSION='.$esperada);
            foreach (['10.4.33-MariaDB', '10.11.15-MariaDB', '8.0.32', $esperada.'-MySQL',
                '10.4.32-MariaDB', '10.11.14-MariaDB', '110.11.14-MariaDB', ''] as $real) {
                if ($real === $esperada.'-MariaDB') continue;
                try {
                    Guard::verificar($this->pdoSimulado($real));
                    $this->fail('Debe rechazar la versión real.');
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('versión real', $e->getMessage());
                }
            }
        }
    }

    public function test_rechaza_seleccion_desconocida_antes_de_consultar(): void
    {
        foreach (['', '10.11', '10.11.15', ' 10.11.14', '10.4.32 ', '10.11.14-MariaDB'] as $valor) {
            putenv('KARMA_CAJA_EXPECTED_VERSION='.$valor);
            $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['query'])->getMock();
            $pdo->expects($this->never())->method('query');
            try {
                Guard::verificar($pdo);
                $this->fail('Debe rechazar la selección.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('KARMA_CAJA_EXPECTED_VERSION', $e->getMessage());
            }
        }
    }

    public function test_rechaza_cuentas_autenticadas_distintas_sin_publicarlas(): void
    {
        foreach (['root@localhost', '@localhost', 'karma_caja_it@%', 'karma_caja_it@remoto', '', 'karma_caja_it_otro@localhost'] as $cuenta) {
            try {
                Guard::verificar($this->pdoSimulado('10.4.32-MariaDB', $cuenta));
                $this->fail('Debe rechazar la identidad autenticada.');
            } catch (RuntimeException $e) {
                $this->assertSame('STOP: la cuenta autenticada no es la cuenta dedicada local permitida.', $e->getMessage());
            }
        }
    }

    public function test_configuracion_invalida_se_detiene_antes_de_conectar(): void
    {
        $valida = ['ALLOW' => Guard::BASE, 'DB' => Guard::BASE, 'HOST' => '127.0.0.1',
            'PORT' => '3308', 'USER' => 'karma_caja_it', 'PASSWORD' => 'solo-sintetica'];
        foreach (['EXPECTED_VERSION' => '10.11', 'ALLOW' => '', 'DB' => 'cafeteria_pro',
            'HOST' => 'remoto', 'PORT' => '0', 'USER' => 'root', 'PASSWORD' => ''] as $campo => $invalido) {
            putenv('KARMA_CAJA_EXPECTED_VERSION');
            foreach ($valida as $key => $valor) putenv('KARMA_CAJA_'.$key.'='.$valor);
            putenv('KARMA_CAJA_'.$campo.'='.$invalido);
            try {
                Guard::boot();
                $this->fail('Debe rechazar la configuración antes de construir PDO.');
            } catch (RuntimeException $e) {
                $this->assertStringStartsWith('STOP:', $e->getMessage());
            }
        }
    }

    private function pdoSimulado(string $version, string $cuenta = 'karma_caja_it@localhost'): PDO
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetch')->willReturn(['base_actual' => Guard::BASE,
            'version_servidor' => $version, 'sesion' => 123, 'cuenta_autenticada' => $cuenta]);
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['query'])->getMock();
        $pdo->expects($this->once())->method('query')->willReturn($statement);
        return $pdo;
    }
}
