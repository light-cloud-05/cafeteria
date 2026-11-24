<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Demostracion\{GuardiaInstalacionDemo as Guardia, InstaladorEsquemaDemo, RechazoInstalacionDemo};
use PHPUnit\Framework\TestCase;

/** Pruebas puras: no arrancan Laravel, no abren PDO ni ejecutan migraciones. */
class InstalacionDemoTest extends TestCase
{
    private function config(): array
    {
        return ['APP_ENV'=>'demostracion','CAJA_DEMO_ACTIVA'=>'true','DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1',
            'DB_PORT'=>'3308','DB_DATABASE'=>'cafeteria_defensa_demo','DB_USERNAME'=>'karma_defensa_demo','CAJA_DEMO_SERVIDOR'=>'servidor-ficticio'];
    }
    private function identidad(): array
    {
        return ['base'=>'cafeteria_defensa_demo','cuenta'=>'karma_demo_instalador@localhost','usuario'=>'karma_demo_instalador@127.0.0.1',
            'servidor'=>'servidor-ficticio','puerto'=>3308,'rol'=>'NONE','version'=>'10.4.32-MariaDB','motor'=>'InnoDB','fk'=>1,
            'modo'=>'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'];
    }
    private function grant(string $privilegios='ALL PRIVILEGES',string $objeto='`cafeteria\\_defensa\\_demo`.*',string $extra=''): string
    {
        return "GRANT {$privilegios} ON {$objeto} TO `karma_demo_instalador`@`localhost`{$extra}";
    }
    private function rechaza(callable $operacion): void
    {
        try { $operacion();$this->fail('La guarda debió rechazar.'); }
        catch (RechazoInstalacionDemo $e) { $this->assertNotSame('',$e->getMessage()); }
    }
    public function test_configuracion_admite_solo_destino_demo_fijo(): void
    {
        Guardia::configuracion($this->config());
        foreach (['APP_ENV'=>'production','CAJA_DEMO_ACTIVA'=>'false','DB_CONNECTION'=>'sqlite','DB_HOST'=>'localhost',
            'DB_PORT'=>'3306','DB_DATABASE'=>'cafeteria_pro','DB_USERNAME'=>'karma_demo_instalador','CAJA_DEMO_SERVIDOR'=>'',
            'DATABASE_URL'=>'alternativo','DB_URL'=>'alternativo','DB_SOCKET'=>'alternativo','MYSQL_ATTR_SSL_CA'=>'alternativa'] as $campo=>$valor) {
            $this->rechaza(fn()=>Guardia::configuracion(array_replace($this->config(),[$campo=>$valor])));
        }
    }
    public function test_identidad_rechaza_cuenta_host_puerto_rol_y_motor_incorrectos(): void
    {
        Guardia::identidad($this->identidad(),'servidor-ficticio');
        foreach (['base'=>'cafeteria_pro','cuenta'=>'root@localhost','usuario'=>'karma_defensa_demo@localhost',
            'servidor'=>'otro','puerto'=>3306,'rol'=>'administrador','version'=>'8.0-MySQL','motor'=>'MyISAM','fk'=>0,'modo'=>''] as $campo=>$valor) {
            $this->rechaza(fn()=>Guardia::identidad(array_replace($this->identidad(),[$campo=>$valor]),'servidor-ficticio'));
        }
    }
    public function test_grants_solo_de_base_literal_y_sin_privilegios_globales(): void
    {
        Guardia::grants([$this->grant()]);
        Guardia::grants([$this->grant(implode(', ',Guardia::PRIVILEGIOS))]);
        foreach ([$this->grant('ALL PRIVILEGES','*.*'),$this->grant('ALL PRIVILEGES','`cafeteria_defensa_demo`.*'),
            $this->grant('ALL PRIVILEGES','`otra`.*'),$this->grant('ALL PRIVILEGES','`cafeteria\\_defensa\\_demo`.*',' WITH GRANT OPTION'),
            $this->grant('SELECT'),$this->grant('FILE'),"GRANT `rol` TO `karma_demo_instalador`@`localhost`"] as $grant) {
            $this->rechaza(fn()=>Guardia::grants([$grant]));
        }
        $this->rechaza(fn()=>Guardia::grants([]));
        foreach (Guardia::PRIVILEGIOS as $omitir) {
            $privilegios=array_values(array_diff(Guardia::PRIVILEGIOS,[$omitir]));
            $this->rechaza(fn()=>Guardia::grants([$this->grant(implode(', ',$privilegios))]));
        }
    }
    public function test_inventario_exige_cero_tablas_vistas_triggers_rutinas_y_eventos(): void
    {
        $vacio=['tablas_vistas'=>0,'triggers'=>0,'rutinas'=>0,'eventos'=>0];
        Guardia::vacia($vacio);
        foreach (array_keys($vacio) as $campo) {
            $this->rechaza(fn()=>Guardia::vacia(array_replace($vacio,[$campo=>1])));
            $incompleto=$vacio;unset($incompleto[$campo]);
            $this->rechaza(fn()=>Guardia::vacia($incompleto));
        }
        $this->rechaza(fn()=>Guardia::vacia(array_replace($vacio,['eventos'=>null])));
    }
    public function test_manifiesto_solo_lee_migraciones_y_preserva_orden(): void
    {
        $directorio=dirname(__DIR__,2).'/database/migrations';
        $manifest=InstaladorEsquemaDemo::manifiesto($directorio);
        $this->assertCount(count(glob($directorio.'/*.php')),$manifest);
        $orden=array_keys($manifest);$esperado=$orden;sort($esperado,SORT_STRING);
        $this->assertSame($esperado,$orden);
        $this->assertNotEmpty($manifest);
        // Solo inspección de archivos, sin require de migraciones ni conexión de base.
    }
}
