<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{CierreCajaVersion, User};
use App\Services\Caja\{CoordinacionCobrosCaja, CoordinadorCobrosCaja, FalloCoordinacionCaja};
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class CajaFormulariosTest extends TestCase
{
    private int $cierre;
    private array $permisos = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) throw new LogicException('Solo SQLite :memory:.');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach (DB::select('PRAGMA database_list') as $base) $this->assertSame('', $base->file);
        foreach (glob(database_path('migrations/*.php')) as $archivo) (require $archivo)->up();
        DB::table('roles')->insert(['rol_id' => 1, 'nombre' => 'Responsable sintético', 'estado' => 1]);
        DB::table('usuarios')->insert(['id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Actor sintético',
            'nombreUsuario' => 'formularios-caja', 'password' => 'fixture', 'estado' => 1]);
        foreach (['consultar_caja', 'confirmar_cierre_caja', 'rectificar_cierre_caja'] as $nombre) {
            $this->permisos[$nombre] = DB::table('permisos')->insertGetId(['nombre' => $nombre]);
            DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $this->permisos[$nombre]]);
        }
        $this->actingAs(User::findOrFail(1));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 12:00:00', 'America/La_Paz'));
        $this->cierre = DB::table('cierre_cajas')->insertGetId(['idUsuario' => 1, 'fecha_operativa' => '2026-10-05', 'fechaInicio' => '2026-10-05 04:00:00']);
        DB::table('cierre_caja_versiones')->insert(['cierre_caja_id' => $this->cierre, 'numero_version' => 1,
            'responsable_id' => 1, 'registrado_en' => '2026-10-06 04:00:00', 'total_ventas' => '0.00',
            'efectivo_esperado' => '0.00', 'qr_registrado' => '0.00', 'efectivo_contado' => '0.00',
            'observacion' => '<script>observacion()</script>']);
        // Activa realmente CSRF: PHPUnit por defecto lo omite en el framework.
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken {
            protected function runningUnitTests() { return false; }
        });
        $this->withSession(['_token' => 'csrf-sintetico-caja']);
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }

    private function permitir(?Closure $antes = null, ?Closure $despues = null): void
    {
        $this->app->instance(CoordinacionCobrosCaja::class, new class($antes, $despues) implements CoordinacionCobrosCaja {
            public function __construct(private ?Closure $antes, private ?Closure $despues) {}
            public function permiteCierreDefinitivo(): bool { return true; }
            public function exclusivo(Closure $operacion): mixed
            {
                if (!app()->runningUnitTests() || DB::connection()->getDriverName() !== 'sqlite'
                    || DB::connection()->getDatabaseName() !== ':memory:') throw new LogicException('Solo pruebas aisladas.');
                ($this->antes ?? static fn () => null)();
                $resultado = (new CoordinadorCobrosCaja)->exclusivo($operacion);
                ($this->despues ?? static fn () => null)();
                return $resultado;
            }
        });
    }

    private function confirmar(array $datos = [])
    {
        return $this->post('/admin/caja/cierres', array_replace(['_token' => 'csrf-sintetico-caja',
            'fecha' => '2026-10-06', 'efectivo_contado' => '0.00', 'observacion' => null], $datos));
    }

    private function rectificar(array $datos = [])
    {
        return $this->post('/admin/caja/cierres/'.$this->cierre.'/rectificaciones', array_replace([
            '_token' => 'csrf-sintetico-caja', 'version_esperada' => '1', 'efectivo_contado' => '0.01',
            'observacion' => 'Moneda encontrada', 'motivo' => 'Recuento supervisado'], $datos));
    }

    public function test_gate_real_bloquea_post_incluso_con_csrf_y_permisos_y_no_genera_eventos(): void
    {
        $this->confirmar()->assertStatus(503)->assertSee('No se inició la operación');
        $this->rectificar()->assertStatus(503)->assertSee('todavía no están habilitadas');
        $this->assertSame(1, DB::table('cierre_cajas')->count());
        $this->assertSame(1, DB::table('cierre_caja_versiones')->count());
        $this->assertSame(0, DB::table('bitacoras')->count());
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
        $this->get('/admin/caja/confirmacion')->assertOk()->assertSee('<fieldset disabled>', false)->assertSee('name="_token"', false);
        $this->get('/admin/caja/cierres/'.$this->cierre.'/rectificacion')->assertOk()->assertSee('<fieldset disabled>', false);
    }

    public function test_csrf_ausente_invalido_y_valido_en_ambas_operaciones(): void
    {
        $this->permitir();
        $this->confirmar(['_token' => null])->assertStatus(419);
        $this->confirmar(['_token' => 'invalido'])->assertStatus(419);
        $this->rectificar(['_token' => null])->assertStatus(419);
        $this->rectificar(['_token' => 'invalido'])->assertStatus(419);
        $this->assertSame(0, DB::table('bitacoras')->count());
        $this->confirmar()->assertRedirect();
        $this->rectificar()->assertRedirect(route('admin.caja.show', $this->cierre));
        $this->assertSame(2, DB::table('cierre_cajas')->count());
        $this->assertSame(3, DB::table('cierre_caja_versiones')->count());
    }

    public function test_permisos_especificos_y_consulta_son_obligatorios(): void
    {
        foreach (['confirmar_cierre_caja' => ['/admin/caja/confirmacion', '/admin/caja/cierres'],
            'rectificar_cierre_caja' => ['/admin/caja/cierres/'.$this->cierre.'/rectificacion', '/admin/caja/cierres/'.$this->cierre.'/rectificaciones']] as $nombre => [$get,$post]) {
            DB::table('rol_permisos')->where('idPermiso', $this->permisos[$nombre])->delete();
            $this->get($get)->assertForbidden();
            $this->post($post, ['_token' => 'csrf-sintetico-caja'])->assertForbidden();
            DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $this->permisos[$nombre]]);
        }
        DB::table('rol_permisos')->where('idPermiso', $this->permisos['consultar_caja'])->delete();
        $this->confirmar()->assertForbidden(); $this->rectificar()->assertForbidden();
        $this->get('/admin/caja/confirmacion')->assertForbidden();
        $this->assertSame(0, DB::table('bitacoras')->count());
    }

    public function test_validaciones_fecha_dinero_observacion_motivo_y_version(): void
    {
        $this->permitir();
        foreach ([['fecha' => '2026-10-07'], ['fecha' => '2026-10-08'], ['fecha' => '2026-02-30'],
            ['efectivo_contado' => '-1.00'], ['efectivo_contado' => '0.001'], ['efectivo_contado' => 1.1],
            ['efectivo_contado' => '1e3'], ['efectivo_contado' => '1,20'], ['observacion' => str_repeat('x', 4001)],
            ['efectivo_contado' => '1.00', 'observacion' => '']] as $datos) $this->confirmar($datos)->assertStatus(422);
        foreach ([['motivo' => ''], ['motivo' => str_repeat('x', 1001)], ['version_esperada' => '0'],
            ['version_esperada' => '2'], ['motivo' => 'password=NO_PUBLICAR'], ['version_esperada' => []]] as $datos) {
            $this->rectificar($datos)->assertStatus(422)->assertDontSee('NO_PUBLICAR');
        }
        $this->assertSame(1, DB::table('cierre_caja_versiones')->count());
    }

    public function test_rechaza_importes_actor_y_fecha_inyectados_sin_aceptarlos_como_autoridad(): void
    {
        $this->permitir();
        foreach (['total_ventas','diferencia','responsable_id','idUsuario','usuario_id','monto_qr'] as $campo) {
            $this->confirmar([$campo => '999'])->assertStatus(422);
            $this->rectificar([$campo => '999'])->assertStatus(422);
        }
        $this->rectificar(['fecha' => '2026-10-01'])->assertStatus(422);
        $this->assertSame(1, DB::table('cierre_caja_versiones')->count());
        $this->assertSame(0, DB::table('bitacoras')->count());
    }

    public function test_actor_de_sesion_y_calculos_y_nueva_version_son_del_servicio(): void
    {
        $this->permitir();
        $this->confirmar(['efectivo_contado' => '0.01', 'observacion' => 'Moneda'])->assertRedirect();
        $nueva = CierreCajaVersion::orderByDesc('id')->firstOrFail();
        $this->assertSame(1, $nueva->responsable_id);
        $this->assertSame('0.00', $nueva->total_ventas);
        $this->assertSame('0.01', $nueva->diferencia);
        $this->rectificar()->assertRedirect();
        $this->rectificar()->assertStatus(422); // La misma versión esperada ya es obsoleta.
        $this->assertSame(1, DB::table('bitacoras')->where('accion', 'RECTIFICACION_CAJA')->count());
    }

    public function test_revocacion_durante_espera_rechaza_antes_de_escribir(): void
    {
        $this->permitir(fn () => DB::table('rol_permisos')->where('idPermiso', $this->permisos['confirmar_cierre_caja'])->delete());
        $this->confirmar()->assertForbidden();
        $this->assertSame(1, DB::table('cierre_cajas')->count());
        $this->permitir(fn () => DB::table('rol_permisos')->where('idPermiso', $this->permisos['rectificar_cierre_caja'])->delete());
        $this->rectificar()->assertForbidden();
        $this->assertSame(1, DB::table('cierre_caja_versiones')->count());
        $this->assertSame(0, DB::table('bitacoras')->whereIn('accion', ['CIERRE_CAJA','RECTIFICACION_CAJA'])->count());
    }

    public function test_timeout_y_resultado_incierto_no_reintentan_ni_exponen_excepciones(): void
    {
        $intentos = 0;
        $this->permitir(function () use (&$intentos) { $intentos++; throw \Illuminate\Validation\ValidationException::withMessages(['caja' => 'Timeout SQLSTATE secreto']); });
        $this->confirmar()->assertStatus(422)->assertDontSee('SQLSTATE')->assertDontSee('secreto');
        $this->assertSame(1, $intentos);
        $this->permitir(null, function () use (&$intentos) { $intentos++; throw new FalloCoordinacionCaja('SECRETO_SQL', true); });
        $this->rectificar()->assertStatus(409)->assertSee('resultado es incierto')->assertDontSee('SECRETO_SQL');
        $this->assertSame(2, $intentos);
        $this->assertSame(2, DB::table('cierre_caja_versiones')->count());
        $this->assertSame(1, DB::table('bitacoras')->where('accion','RECTIFICACION_CAJA')->count());
    }

    public function test_formularios_escapados_sin_totales_editables_ni_borradores(): void
    {
        foreach (['/admin/caja/confirmacion', '/admin/caja/cierres/'.$this->cierre.'/rectificacion'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('data-borrador', false)->assertDontSee('localStorage', false)
                ->assertDontSee('sessionStorage', false)->assertDontSee('name="responsable_id"', false)
                ->assertDontSee('name="total_ventas"', false)->assertDontSee('name="diferencia"', false);
        }
        $this->get('/admin/caja/cierres/'.$this->cierre.'/rectificacion')->assertOk()
            ->assertSee('<script>observacion()</script>')->assertDontSee('<script>observacion()</script>', false);
    }

    public function test_habilitacion_aislada_no_funciona_fuera_del_entorno_de_pruebas(): void
    {
        $this->permitir();
        $this->app->instance('env', 'production');
        try {
            $this->confirmar()->assertStatus(503);
            $this->rectificar()->assertStatus(503);
            $this->assertSame(1, DB::table('cierre_caja_versiones')->count());
            $this->assertSame(0, DB::table('bitacoras')->count());
        } finally { $this->app->instance('env', 'testing'); }
    }

    public function test_invitados_y_roles_inactivos_no_acceden_a_formularios_ni_operaciones(): void
    {
        DB::table('roles')->update(['estado' => 0]);
        $this->actingAs(User::findOrFail(1));
        $this->get('/admin/caja/confirmacion')->assertForbidden();
        $this->confirmar()->assertForbidden(); $this->rectificar()->assertForbidden();
        \Illuminate\Support\Facades\Auth::logout();
        $this->get('/admin/caja/confirmacion')->assertRedirect(route('login'));
        $this->confirmar()->assertRedirect(route('login'));
    }

    public function test_excepcion_posterior_al_commit_no_se_presenta_como_rollback(): void
    {
        $this->permitir(null, static fn () => throw new \RuntimeException('SQLSTATE password=SECRETO'));
        $this->confirmar()->assertStatus(409)->assertSee('No se pudo confirmar el resultado')
            ->assertSee('podría haberse registrado')->assertDontSee('SQLSTATE')->assertDontSee('SECRETO');
        $this->assertSame(2, DB::table('cierre_cajas')->count());
        $this->assertSame(1, DB::table('bitacoras')->where('accion', 'CIERRE_CAJA')->count());
    }

    public function test_solo_se_publican_acciones_explicitas_de_caja_sin_edicion_ni_eliminacion(): void
    {
        $rutas = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'admin/caja'));
        $this->assertCount(10, $rutas);
        foreach ($rutas as $ruta) {
            foreach (['auth','permiso:consultar_caja'] as $middleware) $this->assertContains($middleware, $ruta->gatherMiddleware());
            $this->assertEmpty(array_intersect(['PUT','PATCH','DELETE'], $ruta->methods()));
            if (str_ends_with($ruta->getName(), '.confirmar') || str_ends_with($ruta->getName(), '.confirmacion')) {
                $this->assertContains('permiso:confirmar_cierre_caja', $ruta->gatherMiddleware());
            }
            if (str_ends_with($ruta->getName(), '.rectificar') || str_ends_with($ruta->getName(), '.rectificacion')) {
                $this->assertContains('permiso:rectificar_cierre_caja', $ruta->gatherMiddleware());
            }
        }
        $this->put('/admin/caja/cierres/'.$this->cierre, ['_token'=>'csrf-sintetico-caja'])->assertStatus(405);
        $this->delete('/admin/caja/cierres/'.$this->cierre, ['_token'=>'csrf-sintetico-caja'])->assertStatus(405);
    }

    public function test_renderiza_formularios_bloqueados_y_de_prueba_para_revision_visual_aislada(): void
    {
        $directorio = getenv('KARMA_CAJA_HTML_DIR');
        if ($directorio) {
            $real = realpath($directorio); $temporal = realpath(sys_get_temp_dir());
            if (!$real || !$temporal || !str_starts_with(strtolower($real.DIRECTORY_SEPARATOR), strtolower($temporal.DIRECTORY_SEPARATOR))) throw new LogicException('Solo exportación temporal.');
        }
        foreach (['bloqueada', 'prueba'] as $estado) {
            if ($estado === 'prueba') $this->permitir();
            foreach (['confirmacion' => '/admin/caja/confirmacion', 'rectificacion' => '/admin/caja/cierres/'.$this->cierre.'/rectificacion'] as $nombre=>$url) {
                $respuesta = $this->get($url)->assertOk()->assertSee('name="_token"', false);
                if ($estado === 'bloqueada') $respuesta->assertSee('<fieldset disabled>', false);
                else $respuesta->assertDontSee('<fieldset disabled>', false);
                if ($directorio) file_put_contents($real.DIRECTORY_SEPARATOR.$nombre.'-'.$estado.'.html', $respuesta->getContent());
            }
        }
    }

    public function test_validacion_lanzada_despues_del_commit_no_afirma_rechazo_financiero(): void
    {
        $this->permitir();
        CierreCajaVersion::created(static function () {
            DB::afterCommit(static fn () => throw \Illuminate\Validation\ValidationException::withMessages(['caja' => 'SECRETO_POSTCOMMIT']));
        });
        $this->confirmar()->assertStatus(422)->assertSee('podría haberse registrado')
            ->assertDontSee('La solicitud fue rechazada')->assertDontSee('SECRETO_POSTCOMMIT');
        $this->assertSame(2, DB::table('cierre_cajas')->count());
        $this->rectificar()->assertStatus(422)->assertSee('podría haberse registrado')
            ->assertDontSee('La solicitud fue rechazada')->assertDontSee('SECRETO_POSTCOMMIT');
        $this->assertSame(3, DB::table('cierre_caja_versiones')->count());
        $this->assertSame(1, DB::table('bitacoras')->where('accion', 'CIERRE_CAJA')->count());
        $this->assertSame(1, DB::table('bitacoras')->where('accion', 'RECTIFICACION_CAJA')->count());
    }

    public function test_gate_real_rechaza_antes_de_resolver_servicios_financieros(): void
    {
        $this->app->bind(\App\Services\Caja\CierreDiarioService::class, static fn () => throw new LogicException('No se debe resolver el servicio de cierre.'));
        $this->app->bind(\App\Services\Caja\RectificacionCierreDiarioService::class, static fn () => throw new LogicException('No se debe resolver el servicio de rectificación.'));
        $this->confirmar()->assertStatus(503)->assertSee('No se inició la operación');
        $this->rectificar()->assertStatus(503)->assertSee('No se inició la operación');
        $this->assertSame(0, DB::table('bitacoras')->count());
    }

    public function test_actualizar_en_resultados_post_solo_navega_a_consulta_get(): void
    {
        foreach ([$this->confirmar(), $this->rectificar()] as $respuesta) {
            $respuesta->assertStatus(503)->assertDontSee('window.location.reload()', false);
            $dom = new \DOMDocument();
            @$dom->loadHTML($respuesta->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//a[contains(@class,"boton-actualizar") and @href="'.route('admin.caja.index').'"]')->length);
        }
    }

    public function test_las_diez_rutas_rechazan_sin_consultar_caja_antes_de_leer_finanzas(): void
    {
        DB::table('rol_permisos')->where('idPermiso', $this->permisos['consultar_caja'])->delete();
        DB::enableQueryLog(); DB::flushQueryLog();
        foreach (['/admin/caja', '/admin/caja/mensual', '/admin/caja/anual', '/admin/caja/previsualizacion', '/admin/caja/cierres/'.$this->cierre,
            '/admin/caja/cierres/'.$this->cierre.'/versiones/1', '/admin/caja/confirmacion',
            '/admin/caja/cierres/'.$this->cierre.'/rectificacion'] as $url) $this->get($url)->assertForbidden();
        $this->confirmar()->assertForbidden(); $this->rectificar()->assertForbidden();
        $sql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        foreach (['cierre_cajas', 'cierre_caja_versiones', 'cierre_caja_version_ventas', 'bitacoras', 'from "ventas"', 'get_lock'] as $tabla) {
            $this->assertStringNotContainsString($tabla, $sql);
        }
    }

    public function test_autorizacion_excepcional_posterior_al_commit_no_expone_secretos_ni_afirma_rollback(): void
    {
        config(['app.debug'=>true]);
        $this->permitir(null, static fn()=>throw new \Illuminate\Auth\Access\AuthorizationException('SQLSTATE password=SECRETO_POSTCOMMIT'));
        $this->confirmar()->assertStatus(403)->assertDontSee('SECRETO_POSTCOMMIT')->assertDontSee('SQLSTATE')
            ->assertSee('podría haberse registrado');
        $this->assertSame(2,DB::table('cierre_cajas')->count());
        $this->assertSame(1,DB::table('bitacoras')->where('accion','CIERRE_CAJA')->count());
    }

    public function test_no_hay_rutas_genericas_para_reescribir_cierres_versiones_o_bitacoras(): void
    {
        foreach (['/admin/caja/cierres/'.$this->cierre,
            '/admin/caja/cierres/'.$this->cierre.'/versiones/1', '/admin/bitacora/1'] as $url) {
            foreach (['put','patch','delete'] as $verbo) $this->{$verbo}($url, ['_token'=>'csrf-sintetico-caja'])->assertStatus(405);
        }
        $this->assertSame(1, DB::table('cierre_caja_versiones')->count());
        $this->assertSame(0, DB::table('bitacoras')->count());
    }

    public function test_sesion_invalidada_despues_del_commit_no_rompe_layout_ni_expone_excepcion(): void
    {
        config(['app.debug'=>true]);
        $this->permitir(null, static function () {
            \Illuminate\Support\Facades\Auth::logout();
            throw new RuntimeException('password=SECRETO_SQL');
        });
        $this->confirmar()->assertStatus(401)->assertSee('sin reenviar la operación')->assertDontSee('SECRETO_SQL')->assertDontSee('Stack trace');
        $this->assertSame(2,DB::table('cierre_cajas')->count());
    }
}
