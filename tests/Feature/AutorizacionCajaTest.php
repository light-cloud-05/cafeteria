<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerificarPermiso;
use App\Models\{CierreCaja, CierreCajaVersion, CierreCajaVersionVenta, User};
use App\Services\Caja\{AutorizacionCaja, CierreDiarioService, CoordinacionCobrosCaja, CoordinadorCobrosCaja};
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AutorizacionCajaTest extends TestCase
{
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new LogicException('AutorizacionCajaTest exige SQLite :memory: sin URL alternativa.');
        }
        foreach (DB::select('PRAGMA database_list') as $base) {
            $this->assertSame('', $base->file);
        }
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            (require $archivo)->up();
        }
        DB::table('roles')->insert([
            ['rol_id' => 1, 'nombre' => 'Administrador', 'estado' => 1],
            ['rol_id' => 2, 'nombre' => 'Rol sin nombre privilegiado', 'estado' => 1],
        ]);
        DB::table('usuarios')->insert(['id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Actor prueba autorización',
            'nombreUsuario' => 'autorizacion-caja', 'password' => 'no-es-una-credencial', 'estado' => 1]);
        foreach ([AutorizacionCaja::CONSULTAR, AutorizacionCaja::CONFIRMAR, AutorizacionCaja::RECTIFICAR] as $nombre) {
            $this->ids[$nombre] = DB::table('permisos')->insertGetId(['nombre' => $nombre]);
        }
        $this->actingAs(User::findOrFail(1));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 12:00:00', 'America/La_Paz'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function conceder(string $permiso, int $rol = 1): void
    {
        DB::table('rol_permisos')->insert(['idRol' => $rol, 'idPermiso' => $this->ids[$permiso]]);
    }

    private function coordinador(?Closure $antes = null): CoordinacionCobrosCaja
    {
        return new class($antes) implements CoordinacionCobrosCaja {
            public int $entradas = 0;
            public function __construct(private ?Closure $antes) {}
            public function permiteCierreDefinitivo(): bool { return true; }
            public function exclusivo(Closure $operacion): mixed
            {
                if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
                    throw new LogicException('Solo simulación secuencial en memoria.');
                }
                $this->entradas++;
                ($this->antes ?? static fn () => null)();
                return $operacion();
            }
        };
    }

    private function sinEscriturasFinancieras(): void
    {
        $this->assertSame(0, CierreCaja::count());
        $this->assertSame(0, CierreCajaVersion::count());
        $this->assertSame(0, CierreCajaVersionVenta::count());
        $this->assertSame(0, DB::table('bitacoras')->where('accion', 'CIERRE_CAJA')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_administrador_sin_permisos_no_consulta_ni_adquiere_el_mutex(): void
    {
        $coordinador = $this->coordinador();
        $servicio = new CierreDiarioService($coordinador);
        DB::connection()->enableQueryLog();
        foreach (['previsualizar', 'confirmar'] as $accion) {
            try {
                $accion === 'previsualizar' ? $servicio->previsualizar('2026-10-06') : $servicio->confirmar('2026-10-06', '0');
                $this->fail('El nombre del rol no debe conceder permisos.');
            } catch (AuthorizationException) {
                $this->assertSame(0, $coordinador->entradas);
            }
        }
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        $this->assertStringNotContainsString('"ventas"', $sql);
        $this->assertStringNotContainsString('"cierre_cajas"', $sql);
        $this->sinEscriturasFinancieras();
    }

    public function test_combinaciones_reales_de_permisos_son_independientes(): void
    {
        $auth = new AutorizacionCaja;
        $this->conceder(AutorizacionCaja::CONSULTAR);
        $this->assertSame(1, $auth->consulta()->id);
        foreach (['confirmacion', 'rectificacion'] as $accion) {
            try {
                $auth->{$accion}();
                $this->fail('Consultar no concede permisos de escritura.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $this->conceder(AutorizacionCaja::CONFIRMAR);
        $this->assertSame(1, $auth->confirmacion()->id);
        $this->conceder(AutorizacionCaja::RECTIFICAR);
        $this->assertSame(1, $auth->rectificacion()->id);
        DB::table('rol_permisos')->where('idPermiso', $this->ids[AutorizacionCaja::CONSULTAR])->delete();
        foreach (['confirmacion', 'rectificacion'] as $accion) {
            try {
                $auth->{$accion}();
                $this->fail('Las operaciones requieren también consulta.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $this->sinEscriturasFinancieras();
    }

    public function test_usuario_sin_sesion_no_puede_previsualizar(): void
    {
        auth()->logout();
        $this->expectException(AuthenticationException::class);
        (new CierreDiarioService)->previsualizar('2026-10-06');
    }

    public function test_estados_y_relaciones_cacheadas_no_evitan_la_revalidacion(): void
    {
        $this->conceder(AutorizacionCaja::CONSULTAR);
        auth()->user()->load('rol');
        foreach (['usuario_inactivo', 'rol_inactivo', 'eliminado'] as $caso) {
            DB::table('usuarios')->where('id', 1)->update(['estado' => 1, 'deleted_at' => null]);
            DB::table('roles')->where('rol_id', 1)->update(['estado' => 1]);
            match ($caso) {
                'usuario_inactivo' => DB::table('usuarios')->where('id', 1)->update(['estado' => 0]),
                'rol_inactivo' => DB::table('roles')->where('rol_id', 1)->update(['estado' => 0]),
                'eliminado' => DB::table('usuarios')->where('id', 1)->update(['deleted_at' => '2026-10-07 04:00:00']),
            };
            try {
                (new AutorizacionCaja)->consulta();
                $this->fail('No debe confiar en el objeto de sesión desactualizado.');
            } catch (AuthenticationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_rol_sin_nombre_especial_funciona_con_asignacion_explicita(): void
    {
        DB::table('usuarios')->where('id', 1)->update(['rol_id' => 2]);
        $this->conceder(AutorizacionCaja::CONSULTAR, 2);
        $this->conceder(AutorizacionCaja::CONFIRMAR, 2);
        $this->assertSame(2, (new AutorizacionCaja)->confirmacion()->rol_id);
    }

    public function test_verificar_permiso_y_servicio_reutilizan_el_mismo_catalogo(): void
    {
        $this->conceder(AutorizacionCaja::CONSULTAR);
        $request = Request::create('/sin-ruta-publicada');
        $request->setUserResolver(fn () => User::findOrFail(1));
        $respuesta = (new VerificarPermiso)->handle($request, static fn () => new Response('ok'), AutorizacionCaja::CONSULTAR);
        $this->assertSame(200, $respuesta->getStatusCode());
        $this->assertSame(1, (new AutorizacionCaja)->consulta()->id);
        DB::table('rol_permisos')->delete();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new VerificarPermiso)->handle($request, static fn () => new Response('no debe entrar'), AutorizacionCaja::CONSULTAR);
    }

    public function test_revocar_permiso_mientras_espera_impide_cualquier_escritura(): void
    {
        $this->conceder(AutorizacionCaja::CONSULTAR);
        $this->conceder(AutorizacionCaja::CONFIRMAR);
        $coordinador = $this->coordinador(function () {
            DB::table('rol_permisos')->where('idPermiso', $this->ids[AutorizacionCaja::CONFIRMAR])->delete();
        });
        try {
            (new CierreDiarioService($coordinador))->confirmar('2026-10-06', '0');
            $this->fail('No debe confirmar con un permiso revocado durante la espera.');
        } catch (AuthorizationException) {
            $this->assertSame(1, $coordinador->entradas);
        }
        $this->sinEscriturasFinancieras();
    }

    public function test_reasignacion_de_rol_mientras_espera_se_rechaza_aunque_el_nuevo_tenga_permisos(): void
    {
        foreach ([1, 2] as $rol) {
            $this->conceder(AutorizacionCaja::CONSULTAR, $rol);
            $this->conceder(AutorizacionCaja::CONFIRMAR, $rol);
        }
        $coordinador = $this->coordinador(fn () => DB::table('usuarios')->where('id', 1)->update(['rol_id' => 2]));
        try {
            (new CierreDiarioService($coordinador))->confirmar('2026-10-06', '0');
            $this->fail('Debe comenzar otra operación con el nuevo contexto.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('rol', $e->getMessage());
        }
        $this->sinEscriturasFinancieras();
    }

    public function test_desactivar_actor_o_rol_mientras_espera_impide_escrituras(): void
    {
        $this->conceder(AutorizacionCaja::CONSULTAR);
        $this->conceder(AutorizacionCaja::CONFIRMAR);
        foreach (['usuarios', 'roles'] as $tabla) {
            DB::table('usuarios')->where('id', 1)->update(['estado' => 1]);
            DB::table('roles')->where('rol_id', 1)->update(['estado' => 1]);
            $coordinador = $this->coordinador(fn () => DB::table($tabla)->where($tabla === 'usuarios' ? 'id' : 'rol_id', 1)->update(['estado' => 0]));
            try {
                (new CierreDiarioService($coordinador))->confirmar('2026-10-06', '0');
                $this->fail('El contexto activo debe revalidarse tras esperar.');
            } catch (AuthenticationException) {
                $this->assertSame(1, $coordinador->entradas);
            }
            $this->sinEscriturasFinancieras();
        }
    }

    public function test_revalidacion_bloquea_rol_antes_del_usuario_y_no_crea_rectificaciones(): void
    {
        foreach (array_keys($this->ids) as $nombre) {
            $this->conceder($nombre);
        }
        $auth = new AutorizacionCaja;
        $previo = $auth->rectificacion();
        DB::connection()->enableQueryLog();
        DB::transaction(function () use ($auth, $previo) {
            DB::flushQueryLog();
            $this->assertSame(1, $auth->revalidarRectificacion($previo)->id);
            $sql = array_column(DB::getQueryLog(), 'query');
            $this->assertStringContainsString('from "roles"', $sql[0]);
            $this->assertStringContainsString('from "usuarios"', $sql[1]);
        });
        $this->sinEscriturasFinancieras();
    }

    public function test_revalidacion_requiere_transaccion_y_gate_de_produccion_sigue_cerrado(): void
    {
        $this->conceder(AutorizacionCaja::CONSULTAR);
        $this->conceder(AutorizacionCaja::CONFIRMAR);
        $auth = new AutorizacionCaja;
        try {
            $auth->revalidarConfirmacion($auth->confirmacion());
            $this->fail('No debe adquirir bloqueos sin transacción coordinada.');
        } catch (LogicException) {
            $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
        }
        try {
            (new CierreDiarioService)->confirmar('2026-10-06', '0');
            $this->fail('Los permisos no habilitan el cierre real.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Confirmación deshabilitada', $e->getMessage());
        }
        $this->sinEscriturasFinancieras();
    }

    public function test_permisos_de_otros_modulos_no_conceden_acceso_a_caja(): void
    {
        foreach (['cobrar_ventas', 'ver_reportes', 'consultar_bitacora', 'gestion_roles'] as $nombre) {
            $id = DB::table('permisos')->insertGetId(['nombre' => $nombre]);
            DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $id]);
        }
        $this->expectException(AuthorizationException::class);
        (new AutorizacionCaja)->consulta();
    }

    public function test_cambio_de_identidad_durante_espera_no_sustituye_al_responsable(): void
    {
        $this->conceder(AutorizacionCaja::CONSULTAR);
        $this->conceder(AutorizacionCaja::CONFIRMAR);
        DB::table('usuarios')->insert(['id' => 2, 'rol_id' => 1, 'nombreCompleto' => 'Segundo actor sintético',
            'nombreUsuario' => 'segundo-actor-caja', 'password' => 'no-es-una-credencial', 'estado' => 1]);
        $coordinador = $this->coordinador(fn () => auth()->setUser(User::findOrFail(2)));
        try {
            (new CierreDiarioService($coordinador))->confirmar('2026-10-06', '0');
            $this->fail('La identidad autorizada inicialmente no puede reemplazarse mientras espera.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('contexto', $e->getMessage());
        }
        $this->sinEscriturasFinancieras();
    }

    public function test_consulta_no_hereda_una_snapshot_de_una_transaccion_del_llamador(): void
    {
        $this->conceder(AutorizacionCaja::CONSULTAR);
        try {
            DB::transaction(fn () => (new CierreDiarioService)->previsualizar('2026-10-06'));
            $this->fail('No debe autorizar con una snapshot previa del llamador.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('autorización inicial', $e->getMessage());
        }
        $this->sinEscriturasFinancieras();
    }
}
