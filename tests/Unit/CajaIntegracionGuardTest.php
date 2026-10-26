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
        $statement->method('fetch')->willReturn(['base_actual' => Guard::BASE, 'version_servidor' => '10.4.32-MariaDB', 'sesion' => 123]);
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['query'])->getMock();
        $pdo->expects($this->once())->method('query')
            ->with('SELECT DATABASE() AS base_actual, VERSION() AS version_servidor, CONNECTION_ID() AS sesion')
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
}
