<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Caja\AislamientoCajaDemo;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOStatement;
use RuntimeException;
use Tests\TestCase;

/** PDO completamente simulado. Ninguna prueba de esta clase abre conexiones reales. */
final class CajaDemoConexionTest extends TestCase
{
    private function sesion(bool $consultaAtributos = true): array
    {
        $estado = (object) ['base' => AislamientoCajaDemo::BASE, 'cuenta' => 'karma_defensa_demo@localhost',
            'transporte' => '127.0.0.1 via TCP/IP', 'persistente' => false, 'consultas' => 0, 'escrituras' => 0,
            'grants' => ['GRANT SELECT, INSERT, UPDATE, DELETE ON `cafeteria\\_defensa\\_demo`.* TO `karma_defensa_demo`@`localhost`']];
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['getAttribute', 'query', 'prepare'])->getMock();
        $pdo->expects($consultaAtributos ? $this->atLeastOnce() : $this->never())->method('getAttribute')->willReturnCallback(fn (int $atributo) => match ($atributo) {
            PDO::ATTR_DRIVER_NAME => 'mysql', PDO::ATTR_PERSISTENT => $estado->persistente,
            PDO::ATTR_CONNECTION_STATUS => $estado->transporte, default => null,
        });
        $pdo->method('query')->willReturnCallback(function (string $sql) use ($estado) {
            $estado->consultas++;
            $statement = $this->createStub(PDOStatement::class);
            $statement->method('fetch')->willReturn(['base' => $estado->base, 'cuenta' => $estado->cuenta,
                'usuario' => 'karma_defensa_demo@localhost', 'servidor' => 'srv-demo', 'puerto' => 3306, 'rol' => null]);
            $statement->method('fetchAll')->willReturn($estado->grants);
            return $statement;
        });
        $pdo->method('prepare')->willReturnCallback(function () use ($estado) {
            $estado->escrituras++;
            return $this->createStub(PDOStatement::class);
        });
        $config = ['driver' => 'mysql', 'database' => AislamientoCajaDemo::BASE, 'username' => AislamientoCajaDemo::CUENTA,
            'host' => '127.0.0.1', 'port' => '3306', 'password' => 'sintetica'];
        $conexion = new MySqlConnection($pdo, AislamientoCajaDemo::BASE, '', $config);
        $this->app['env'] = 'demostracion';
        config(['demostracion.activa' => true, 'demostracion.servidor' => 'srv-demo']);
        $manager = \Mockery::mock(\Illuminate\Database\DatabaseManager::class);
        $manager->shouldReceive('connection')->andReturn($conexion);
        DB::swap($manager);
        return [$estado, $conexion, new AislamientoCajaDemo];
    }

    public function test_revalida_identidad_y_grants_antes_de_cada_escritura(): void
    {
        [$estado, $conexion, $guarda] = $this->sesion();
        $guarda->vigilar();
        $this->assertSame(2, $estado->consultas);
        $estado->cuenta = 'root@localhost';
        try {
            $conexion->statement('INSERT INTO usuarios (id) VALUES (99)');
            $this->fail('No debe llegar a preparar una escritura.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Demostración rechazada', $e->getMessage());
        }
        $this->assertSame(0, $estado->escrituras);
        $estado->cuenta = 'karma_defensa_demo@localhost';
        $estado->grants[] = 'GRANT SELECT ON *.* TO `karma_defensa_demo`@`localhost`';
        $this->expectException(RuntimeException::class);
        $conexion->statement('INSERT INTO usuarios (id) VALUES (99)');
    }

    public function test_rechaza_transporte_persistencia_y_base_efectivos(): void
    {
        foreach (['base' => 'cafeteria_pro', 'transporte' => 'Localhost via UNIX socket', 'persistente' => true] as $campo => $valor) {
            [$estado, , $guarda] = $this->sesion();
            $estado->{$campo} = $valor;
            try {
                $guarda->verificar();
                $this->fail('Debía rechazar la sesión real simulada.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Demostración rechazada', $e->getMessage());
            }
            $this->assertSame(0, $estado->escrituras);
        }
    }

    public function test_rechaza_cambio_de_sesion_y_sql_de_cambio_de_destino(): void
    {
        [$estado, $conexion, $guarda] = $this->sesion();
        $guarda->vigilar();
        foreach (['USE cafeteria_pro', 'GRANT SELECT ON *.* TO demo', 'TRUNCATE usuarios'] as $sql) {
            try { $conexion->statement($sql); $this->fail('SQL prohibido.'); }
            catch (RuntimeException $e) { $this->assertStringContainsString('Demostración rechazada', $e->getMessage()); }
        }
        $nuevo = $this->createStub(PDO::class);
        $conexion->setPdo($nuevo);
        $this->expectException(RuntimeException::class);
        try { $conexion->statement('INSERT INTO usuarios (id) VALUES (99)'); }
        finally { $this->assertSame(0, $estado->escrituras); }
    }

    public function test_activar_bandera_en_produccion_bloquea_arranque_antes_de_consultar_pdo(): void
    {
        [$estado] = $this->sesion(false);
        $this->app['env'] = 'production';
        $provider = new \App\Providers\AppServiceProvider($this->app);
        $provider->register();
        try { $provider->boot(); $this->fail('No debe arrancar con activación en producción.'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('Demostración rechazada', $e->getMessage()); }
        $this->assertSame(0, $estado->consultas);
        $this->assertSame(0, $estado->escrituras);
    }

    public function test_login_identifica_la_demo_y_el_preparador_exige_destino_expreso(): void
    {
        $this->app['env'] = 'demostracion';
        $this->get('/login')->assertOk()->assertSee('DEMOSTRACIÓN ACADÉMICA — DATOS FICTICIOS');
        // Sin aceptación, sale antes de resolver el destino o escribir fixtures.
        $this->artisan('caja:preparar-demo')->expectsOutputToContain('Destino exclusivo: cafeteria_defensa_demo')
            ->expectsOutputToContain('Sin escrituras')->assertFailed();
    }
}
