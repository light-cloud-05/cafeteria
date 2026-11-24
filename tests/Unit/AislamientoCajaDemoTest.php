<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Caja\{AislamientoCajaDemo, CoordinadorCobrosCaja};
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AislamientoCajaDemoTest extends TestCase
{
    private function config(): array
    {
        return ['driver' => 'mysql', 'database' => AislamientoCajaDemo::BASE, 'username' => AislamientoCajaDemo::CUENTA,
            'host' => '127.0.0.1', 'port' => '3306', 'password' => 'sintetica-no-real'];
    }

    public function test_configuracion_expresa_y_gate_normal_cerrado(): void
    {
        AislamientoCajaDemo::validarConfiguracion('demostracion', true, $this->config(), 'servidor-ficticio');
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
    }

    public function test_rechaza_destinos_y_activaciones_incompletas(): void
    {
        foreach ([['production', true, [], 'srv'], ['demostracion', false, [], 'srv'], ['demostracion', 'true', [], 'srv'],
            ['demostracion', true, [], ''], ['demostracion', true, ['database' => 'cafeteria_pro'], 'srv'],
            ['demostracion', true, ['username' => 'root'], 'srv'], ['demostracion', true, ['host' => 'localhost'], 'srv'],
            ['demostracion', true, ['host' => 'remoto'], 'srv'], ['demostracion', true, ['port' => '0'], 'srv'],
            ['demostracion', true, ['url' => 'mysql://otra-base'], 'srv'], ['demostracion', true, ['read' => ['host' => 'otro']], 'srv'],
            ['demostracion', true, ['unix_socket' => '/tmp/mysql.sock'], 'srv'], ['demostracion', true, ['options' => [\PDO::ATTR_PERSISTENT => true]], 'srv'],
            ['demostracion', true, ['options' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'UPDATE usuarios SET estado=0']], 'srv']] as [$env, $activa, $cambio, $servidor]) {
            $this->rechaza(fn () => AislamientoCajaDemo::validarConfiguracion($env, $activa, array_replace($this->config(), $cambio), $servidor));
        }
    }

    public function test_no_confia_en_cuenta_configurada_ni_host_local(): void
    {
        $meta = ['base' => AislamientoCajaDemo::BASE, 'cuenta' => 'karma_defensa_demo@localhost',
            'usuario' => 'karma_defensa_demo@localhost', 'servidor' => 'srv', 'puerto' => 3306, 'rol' => 'NONE'];
        AislamientoCajaDemo::validarIdentidad($meta, 'srv', 3306);
        foreach (['base' => 'cafeteria_pro', 'cuenta' => 'root@localhost', 'usuario' => 'root@localhost',
            'servidor' => 'otro', 'puerto' => 3307, 'rol' => 'administrador'] as $campo => $valor) {
            $this->rechaza(fn () => AislamientoCajaDemo::validarIdentidad(array_replace($meta, [$campo => $valor]), 'srv', 3306));
        }
        $this->addToAssertionCount(1);
    }

    public function test_grants_solo_dml_en_base_literal_sin_roles_ni_privilegios_extra(): void
    {
        $uso = "GRANT USAGE ON *.* TO `karma_defensa_demo`@`localhost` IDENTIFIED BY PASSWORD '*HASH_FICTICIO'";
        $dml = 'GRANT SELECT, INSERT, UPDATE, DELETE ON `cafeteria\\_defensa\\_demo`.* TO `karma_defensa_demo`@`localhost`';
        AislamientoCajaDemo::validarGrants([$uso, $dml]);
        foreach ([[], [$uso], [$dml, 'GRANT rol_admin TO `karma_defensa_demo`@`localhost`'],
            [$dml, 'GRANT SELECT ON *.* TO `karma_defensa_demo`@`localhost`'],
            [str_replace('cafeteria\\_defensa\\_demo', 'cafeteria_defensa_demo', $dml)],
            [str_replace('cafeteria\\_defensa\\_demo', 'cafeteria_pro', $dml)], [$dml.' WITH GRANT OPTION'],
            [str_replace('SELECT, INSERT, UPDATE, DELETE', 'ALL PRIVILEGES', $dml)],
            [$dml, 'GRANT PROXY ON \'root\'@\'localhost\' TO `karma_defensa_demo`@`localhost`'],
            [str_replace('localhost', '%', $dml)], [str_replace('DELETE', 'CREATE', $dml)]] as $grants) {
            $this->rechaza(fn () => AislamientoCajaDemo::validarGrants($grants));
        }
    }

    private function rechaza(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debía rechazar antes de escribir.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Demostración rechazada', $e->getMessage());
            $this->assertStringNotContainsString('HASH_FICTICIO', $e->getMessage());
        }
    }
}
