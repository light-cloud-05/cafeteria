<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use LogicException;
use RuntimeException;
use Tests\Integration\CajaMariaDb\{Catalogo, Consolidados, Guard, Runtime};
use Tests\TestCase;

require_once dirname(__DIR__).'/Integration/CajaMariaDb/Guard.php';
require_once dirname(__DIR__).'/Integration/CajaMariaDb/Catalogo.php';
require_once dirname(__DIR__).'/Integration/CajaMariaDb/Runtime.php';
require_once dirname(__DIR__).'/Integration/CajaMariaDb/Consolidados.php';

class CajaMariaDbConsolidadosFixturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) throw new LogicException('Solo SQLite en memoria.');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach (DB::select('PRAGMA database_list') as $base) $this->assertSame('', $base->file);
        foreach (glob(database_path('migrations/*.php')) as $archivo) (require $archivo)->up();
        Schema::create('caja_it_control', static function (Blueprint $t) { $t->unsignedInteger('id')->primary(); $t->string('marca'); });
        Schema::create('caja_it_runs', static function (Blueprint $t) { $t->id(); $t->string('caso'); });
        DB::table('caja_it_control')->insert(['id'=>1, 'marca'=>Guard::MARCA]);
        DB::table('roles')->insert(['rol_id'=>1,'nombre'=>'Integración sintética','estado'=>1]);
        DB::table('usuarios')->insert(['id'=>1,'rol_id'=>1,'nombreCompleto'=>'Actor sintético integración',
            'nombreUsuario'=>'actor-integracion-caja','password'=>'fixture','estado'=>1]);
        Runtime::prepararAutorizacionActor();
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow();
        \Illuminate\Support\Carbon::setTestNow();
        \Carbon\CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_catalogo_preserva_los_once_casos_y_agrega_seis_sin_ejecutarlos(): void
    {
        $this->assertSame(['dos-cobros','cobro-y-cierre','cierres-duplicados','cobro-durante-cierre',
            'medianoche','timeout','rollback','mismo-pedido','fecha-cerrada','rectificaciones-duplicadas','rectificacion-y-cobro'], Catalogo::casos('concurrencia'));
        $this->assertCount(6,Catalogo::casos('consolidados'));
        $this->assertCount(17,Catalogo::casos('ampliada'));
        $this->assertCount(17,array_unique(Catalogo::casos('ampliada')));
        $this->expectException(RuntimeException::class);
        Catalogo::casos('inexistente');
    }

    public function test_cinco_escenarios_de_fixtures_se_ejecutan_en_memoria_sin_certificar_mariadb(): void
    {
        foreach (array_diff(Catalogo::CONSOLIDADOS,['conso-decimales']) as $caso) {
            $resultado=Consolidados::ejecutar($caso);
            $this->assertSame('PASS',$resultado['estado']);
            $this->assertTrue($resultado['integridad_sin_escrituras']);
            $this->assertFalse($resultado['decimal_mariadb_verificado']);
        }
        $this->assertSame(5,DB::table('caja_it_runs')->count());
    }

    public function test_maximos_decimales_rechazan_sqlite_antes_de_crear_fixtures(): void
    {
        try { Consolidados::ejecutar('conso-decimales'); $this->fail('No certificar DECIMAL con SQLite.'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('requiere DECIMAL real',$e->getMessage()); }
        $this->assertSame(0,DB::table('caja_it_runs')->count());
        $this->assertSame(0,DB::table('cierre_cajas')->count());
    }

    public function test_detector_bloquea_escrituras_antes_de_ejecutarlas(): void
    {
        $guardia=new Consolidados;
        try {
            $guardia->sinEscrituras(static function () {
                DB::table('bitacoras')->insert(['accion'=>'NO_DEBE_INSERTAR']);
                return [];
            });
            $this->fail('Debe impedir cualquier escritura durante la consulta.');
        } catch (RuntimeException $e) { $this->assertStringContainsString('intentó escribir o bloquear',$e->getMessage()); }
        $this->assertSame(0,DB::table('bitacoras')->count());
    }

    public function test_marca_incorrecta_impide_cualquier_fixture_financiero(): void
    {
        DB::table('caja_it_control')->where('id',1)->update(['marca'=>'NO_AUTORIZADA']);
        try { Consolidados::ejecutar('conso-versiones'); $this->fail('Falta marca autorizada.'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('marca',$e->getMessage()); }
        $this->assertSame(0,DB::table('caja_it_runs')->count());
    }

    public function test_huellas_detectan_alteracion_incluso_si_sql_directo_elude_el_hook(): void
    {
        DB::table('cierre_cajas')->insert(['idUsuario'=>1,'fechaInicio'=>'2026-10-01 04:00:00','observacion'=>'Original']);
        $guardia=new Consolidados;
        try {
            $guardia->sinEscrituras(static function () {
                // Prueba negativa SOLO en memoria: un PDO directo no pasa por beforeExecuting de Laravel.
                DB::getPdo()->exec("UPDATE cierre_cajas SET observacion = 'Alteracion sintetica'");
                return [];
            });
            $this->fail('Las huellas deben detectar modificaciones aunque la cantidad no cambie.');
        } catch (RuntimeException $e) { $this->assertStringContainsString('modificaron contenido',$e->getMessage()); }
    }
}
