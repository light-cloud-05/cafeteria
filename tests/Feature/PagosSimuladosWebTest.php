<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\{Pedido, Producto, Stock, TransaccionPago, User, Venta};
use App\Services\Caja\{CoordinacionCobrosCaja, CoordinadorCobrosCaja, FalloCoordinacionCaja};
use App\Services\Pagos\{EstadoPago, IntentosPagoService};
use App\Services\ReservaPedidoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagosSimuladosWebTest extends TestCase
{
    private const TOKEN = 'csrf-ficticio-cu26';

    protected function setUp(): void
    {
        parent::setUp();
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new LogicException('Las pruebas HTTP de pagos exigen SQLite exclusivamente en memoria.');
        }
        foreach (DB::select('PRAGMA database_list') as $base) $this->assertSame('', $base->file);
        foreach (glob(database_path('migrations/*.php')) as $archivo) (require $archivo)->up();
        DB::table('roles')->insert(['rol_id'=>1,'nombre'=>'Cajero ficticio','estado'=>1]);
        DB::table('usuarios')->insert(['id'=>1,'rol_id'=>1,'nombreCompleto'=>'Cajero de prueba',
            'nombreUsuario'=>'cu26-web','password'=>'fixture-no-real','estado'=>1]);
        foreach (['cobrar_ventas','modificar_pedidos'] as $nombre) {
            $id = DB::table('permisos')->insertGetId(['nombre'=>$nombre]);
            DB::table('rol_permisos')->insert(['idRol'=>1,'idPermiso'=>$id]);
        }
        $this->actingAs(User::findOrFail(1));
        DB::table('categorias')->insert(['id'=>1,'nombre'=>'Prueba','estado'=>1]);
        Producto::create(['nombre'=>'Café ficticio','precio'=>'12.35','disponible'=>true,'categoria_id'=>1]);
        Stock::create(['producto_id'=>1,'stock_actual'=>40,'stock_minimo'=>0]);
        config(['pagos.simulacion.activa'=>true,'pagos.simulacion.resultado'=>'PENDIENTE']);
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken {
            protected function runningUnitTests() { return false; }
        });
        $this->withSession(['_token'=>self::TOKEN]);
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }

    private function pedido(): Pedido
    {
        return DB::transaction(function () {
            $p = Pedido::create(['usuario_id'=>1,'fechaHora'=>now(),'estado'=>'Registrado',
                'subtotal'=>'24.70','descuento'=>'0.00','total'=>'24.70']);
            $p->detalles()->create(['producto_id'=>1,'cantidad'=>2,'precioUnitario'=>'12.35','subtotal'=>'24.70','opcion'=>'']);
            app(ReservaPedidoService::class)->mover($p);
            return $p->fresh();
        });
    }

    private function datos(Pedido $p, string $medio = 'QR_SIMULADO'): array
    {
        return ['_token'=>self::TOKEN,'medio'=>$medio,'version_pedido'=>$p->versionCobro(),'clave_idempotencia'=>(string)Str::uuid()];
    }

    private function iniciar(Pedido $p, string $medio = 'QR_SIMULADO'): TransaccionPago
    {
        $this->post(route('admin.pagos.store',$p->id), $this->datos($p,$medio))->assertRedirect();
        return $p->transaccionesPago()->latest('id')->firstOrFail();
    }

    private function escenario(TransaccionPago $t, string $escenario)
    {
        return $this->post(route('admin.pagos.resolver',$t->referencia), ['_token'=>self::TOKEN,'escenario'=>$escenario]);
    }

    public function test_cajero_ve_accesos_preparacion_y_formulario_sin_datos_bancarios(): void
    {
        $p = $this->pedido();
        $this->get(route('admin.indexventa'))->assertOk()->assertSee('SIMULACIÓN LOCAL — SIN DINERO REAL');
        $this->get(route('admin.venta.crear',$p->id))->assertOk()->assertSee('QR simulado')->assertSee('Tarjeta simulada')->assertSee('Cobro presencial');
        $this->get(route('admin.pedido.ver',$p->id))->assertOk()->assertSee('Historial de transacciones');
        $r = $this->get(route('admin.pagos.crear',['id'=>$p->id,'medio'=>'TARJETA_SIMULADA']));
        $r->assertOk()->assertSee('Bs 24.70')->assertSee('name="_token"',false)->assertSee('name="clave_idempotencia"',false)
            ->assertDontSee('name="cvv"',false)->assertDontSee('name="pan"',false);
        $this->assertSame(0, TransaccionPago::count());
        $this->assertSame(0, Venta::count());
    }

    public function test_rutas_exigen_sesion_permiso_y_aislamiento_y_ocultan_accesos(): void
    {
        $p = $this->pedido(); $datos = $this->datos($p);
        auth()->forgetUser();
        $this->get(route('admin.pagos.crear',$p->id))->assertRedirect(route('login'));
        $this->post(route('admin.pagos.store',$p->id),$datos)->assertRedirect(route('login'));
        $this->actingAs(User::findOrFail(1));
        DB::table('rol_permisos')->where('idPermiso',DB::table('permisos')->where('nombre','cobrar_ventas')->value('id'))->delete();
        $this->get(route('admin.pagos.historial'))->assertForbidden();
        $this->post(route('admin.pagos.store',$p->id),$datos)->assertForbidden();
        $this->assertSame(0, TransaccionPago::count());
    }

    public function test_desactivado_o_aislamiento_invalido_bloquea_incluso_un_escenario_valido(): void
    {
        $p = $this->pedido(); $t = $this->iniciar($p);
        config(['pagos.simulacion.activa'=>false]);
        $this->get(route('admin.venta.crear',$p->id))->assertOk()->assertDontSee('QR simulado')->assertSee('Registrar pago');
        $this->get(route('admin.pagos.show',$t->referencia))->assertNotFound();
        $this->escenario($t,'QR_DEMO_APROBADO')->assertNotFound();
        config(['pagos.simulacion.activa'=>true,'database.connections.sqlite.url'=>'no-admitida']);
        $this->get(route('admin.pagos.historial'))->assertNotFound();
        $this->escenario($t,'QR_DEMO_APROBADO')->assertNotFound();
        $this->assertSame(0, Venta::count());
    }

    public static function escenarios(): array
    {
        return [
            ['QR_SIMULADO','QR_DEMO_APROBADO','APROBADO',1],
            ['QR_SIMULADO','QR_DEMO_RECHAZADO','RECHAZADO',0],
            ['QR_SIMULADO','QR_DEMO_PENDIENTE','PENDIENTE',0],
            ['TARJETA_SIMULADA','TARJETA_DEMO_APROBADA','APROBADO',1],
            ['TARJETA_SIMULADA','TARJETA_DEMO_RECHAZADA','RECHAZADO',0],
            ['TARJETA_SIMULADA','TARJETA_DEMO_3DS_PENDIENTE','PENDIENTE',0],
            ['QR_SIMULADO','EXPIRACION_DEMO','EXPIRADO',0],
        ];
    }

    #[DataProvider('escenarios')]
    public function test_escenarios_http_controlados(string $medio, string $escenario, string $estado, int $ventas): void
    {
        $p = $this->pedido(); $t = $this->iniciar($p,$medio);
        $this->get(route('admin.pagos.show',$t->referencia))->assertOk()->assertSee($medio)->assertSee($t->referencia)
            ->assertSee($medio==='QR_SIMULADO' ? 'no escaneable' : '3-D Secure');
        $this->escenario($t,$escenario)->assertRedirect(route('admin.pagos.show',$t->referencia));
        $this->assertSame($estado,$t->fresh()->estado->value);
        $this->assertSame($ventas,Venta::count());
        $this->assertSame($ventas ? 'Pagado' : 'Registrado',$p->fresh()->estado);
        $this->assertSame(38,(int)Stock::first()->stock_actual);
        $this->get(route('admin.pagos.show',$t->referencia))->assertOk()->assertSee($estado)->assertSee('SIMULACION_LOCAL');
        if ($ventas) {
            $this->escenario($t,$escenario)->assertRedirect();
            $this->assertSame(1,Venta::count());
        }
    }

    public function test_inicio_idempotente_y_recarga_de_preparacion_conservan_clave(): void
    {
        $p = $this->pedido(); $url = route('admin.pagos.crear',$p->id);
        $a = $this->get($url)->assertOk()->viewData('borrador');
        $b = $this->get($url)->assertOk()->viewData('borrador');
        $this->assertSame($a,$b);
        $datos = $a + ['_token'=>self::TOKEN];
        $this->post(route('admin.pagos.store',$p->id),$datos)->assertRedirect();
        $t = TransaccionPago::firstOrFail();
        $this->post(route('admin.pagos.store',$p->id),$datos)->assertRedirect(route('admin.pagos.show',$t->referencia));
        $this->get($url)->assertRedirect(route('admin.pagos.show',$t->referencia));
        $this->escenario($t,'QR_DEMO_APROBADO')->assertRedirect();
        $this->post(route('admin.pagos.store',$p->id),$datos)->assertRedirect(route('admin.pagos.show',$t->referencia));
        $this->assertSame(1,TransaccionPago::count());
        $this->assertSame(1,Venta::count());
    }

    public function test_reintento_tras_rechazo_crea_otra_referencia_con_clave_estable(): void
    {
        $p = $this->pedido(); $t = $this->iniciar($p);
        $this->escenario($t,'QR_DEMO_RECHAZADO')->assertRedirect();
        $url = route('admin.pagos.crear',['id'=>$p->id,'medio'=>'QR_SIMULADO','reintento'=>$t->referencia]);
        $a = $this->get($url)->assertOk()->viewData('borrador');
        $b = $this->get($url)->assertOk()->viewData('borrador');
        $this->assertSame($a,$b);
        $this->assertNotSame($t->clave_idempotencia,$a['clave_idempotencia']);
        $this->post(route('admin.pagos.store',$p->id),$a+['_token'=>self::TOKEN])->assertRedirect();
        $this->assertSame(2,TransaccionPago::count());
        $this->assertSame(EstadoPago::RECHAZADO,$t->fresh()->estado);
        $this->assertSame(0,Venta::count());
    }

    public function test_get_no_expira_y_post_consulta_resuelve_vencimiento(): void
    {
        $t = $this->iniciar($this->pedido());
        $this->travel(301)->seconds();
        $this->getJson(route('admin.pagos.show',$t->referencia))->assertOk()->assertJsonPath('estado','PENDIENTE');
        $this->assertSame(EstadoPago::PENDIENTE,$t->fresh()->estado);
        $this->post(route('admin.pagos.consultar',$t->referencia),['_token'=>self::TOKEN])->assertRedirect();
        $this->assertSame(EstadoPago::EXPIRADO,$t->fresh()->estado);
        $this->assertSame(0,Venta::count());
    }

    public function test_cancelacion_e_historial_muestran_referencia_importe_fechas_y_origen(): void
    {
        $p = $this->pedido(); $t = $this->iniciar($p);
        $this->post(route('admin.pagos.cancelar',$t->referencia),['_token'=>self::TOKEN])->assertRedirect();
        $this->get(route('admin.pagos.historial',['pedido'=>$p->id]))->assertOk()->assertSee($t->referencia)
            ->assertSee('CANCELADO')->assertSee('24.70 BOB')->assertSee('SIMULACION_LOCAL')->assertSee('Resolución');
        $this->assertSame(0,Venta::count());
        $this->assertSame(38,(int)Stock::first()->stock_actual);
    }

    public function test_csrf_real_y_metodos_http_protegen_todas_las_mutaciones(): void
    {
        $p = $this->pedido(); $datos = $this->datos($p); unset($datos['_token']);
        $this->post(route('admin.pagos.store',$p->id),$datos)->assertStatus(419);
        $t = $this->iniciar($p);
        foreach (['consultar','resolver','cancelar'] as $accion) {
            $this->post(route('admin.pagos.'.$accion,$t->referencia),['escenario'=>'QR_DEMO_APROBADO'])->assertStatus(419);
            $this->get(route('admin.pagos.'.$accion,$t->referencia))->assertStatus(405);
        }
        $this->assertSame(EstadoPago::PENDIENTE,$t->fresh()->estado);
        $this->assertSame(0,Venta::count());
    }

    public function test_no_admite_estado_importe_tarjeta_ni_escenarios_arbitrarios(): void
    {
        $p = $this->pedido();
        $this->post(route('admin.pagos.store',$p->id),$this->datos($p)+['estado'=>'APROBADO','monto'=>'0.01','pan'=>'dato-no-admitido'])->assertStatus(422);
        $this->assertSame(0,TransaccionPago::count());
        $t = $this->iniciar($p);
        $this->escenario($t,'APROBADO')->assertStatus(422);
        $this->escenario($t,'TARJETA_DEMO_APROBADA')->assertSessionHasErrors('escenario');
        $this->assertSame(0,Venta::count());
    }

    public function test_otro_cajero_puede_consultar_pero_no_resolver_intento_ajeno(): void
    {
        $t = $this->iniciar($this->pedido());
        DB::table('usuarios')->insert(['id'=>2,'rol_id'=>1,'nombreCompleto'=>'Otro cajero',
            'nombreUsuario'=>'otro-web','password'=>'fixture','estado'=>1]);
        $this->actingAs(User::findOrFail(2));
        $this->get(route('admin.pagos.show',$t->referencia))->assertOk()->assertDontSee('Ejecutar escenario local');
        $this->escenario($t,'QR_DEMO_APROBADO')->assertForbidden();
        $this->get(route('admin.pagos.recuperar',['clave_idempotencia'=>$t->clave_idempotencia]))->assertStatus(409);
        $this->assertSame(0,Venta::count());
    }

    public function test_pendiente_oculta_cobro_y_bloquea_edicion_y_cobro_presencial(): void
    {
        $p = $this->pedido(); $t = $this->iniciar($p);
        $this->get(route('admin.venta.crear',$p->id))->assertOk()->assertSee('Consultar intento pendiente')->assertDontSee('Registrar pago');
        $this->post(route('admin.venta.store',$p->id),['_token'=>self::TOKEN,'version_pedido'=>$p->versionCobro(),'metodo_pago'=>'QR'])->assertSessionHasErrors('pedido');
        $this->post(route('admin.pedido.cancelar',$p->id),['_token'=>self::TOKEN])->assertSessionHasErrors('pedido');
        $this->assertSame(1,TransaccionPago::count());
        $this->assertSame(0,Venta::count());
    }

    public function test_resultado_incierto_no_reintenta_y_recupera_misma_solicitud(): void
    {
        $p = $this->pedido(); $datos = $this->datos($p);
        app()->instance(CoordinacionCobrosCaja::class,new class implements CoordinacionCobrosCaja {
            public function permiteCierreDefinitivo(): bool { return false; }
            public function exclusivo(\Closure $operacion): mixed {
                (new CoordinadorCobrosCaja)->exclusivo($operacion);
                throw new FalloCoordinacionCaja('Detalle interno no publicable',true);
            }
        });
        $this->post(route('admin.pagos.store',$p->id),$datos)->assertStatus(503)
            ->assertSee('Consultar solicitud original')->assertDontSee('Detalle interno no publicable');
        $t = TransaccionPago::firstOrFail();
        $this->get(route('admin.pagos.recuperar',['clave_idempotencia'=>$datos['clave_idempotencia']]))->assertRedirect(route('admin.pagos.show',$t->referencia));
        $this->assertSame(1,TransaccionPago::count());
        $this->assertSame(0,Venta::count());
    }

    public function test_aprobacion_incierta_se_consulta_sin_generar_otra_venta(): void
    {
        $t = $this->iniciar($this->pedido());
        app()->instance(CoordinacionCobrosCaja::class,new class implements CoordinacionCobrosCaja {
            public function permiteCierreDefinitivo(): bool { return false; }
            public function exclusivo(\Closure $operacion): mixed {
                (new CoordinadorCobrosCaja)->exclusivo($operacion);
                throw new FalloCoordinacionCaja('Fallo posterior al commit ficticio',true);
            }
        });
        $this->escenario($t,'QR_DEMO_APROBADO')->assertStatus(503)->assertSee('Consultar intento original');
        $this->getJson(route('admin.pagos.show',$t->referencia))->assertOk()->assertJsonPath('estado','APROBADO');
        $this->escenario($t,'QR_DEMO_APROBADO')->assertStatus(503);
        $this->assertSame(1,Venta::count());
        $this->assertSame(1,TransaccionPago::count());
    }

    public function test_vistas_renderizadas_y_exportacion_opcional_de_fixtures_sinteticos(): void
    {
        $directorio = getenv('CU26_VISTAS_DIR');
        if ($directorio) {
            $real = realpath($directorio);
            $temporal = realpath(sys_get_temp_dir());
            if (!$real || !$temporal || !str_starts_with(strtolower($real.DIRECTORY_SEPARATOR), strtolower($temporal.DIRECTORY_SEPARATOR))) {
                throw new LogicException('Los fixtures visuales solo se exportan a un directorio temporal existente.');
            }
        }
        $guardar = function (string $nombre, $respuesta, int $estado = 200) use ($directorio) {
            $respuesta->assertStatus($estado)->assertSee('SIMULACIÓN LOCAL — SIN DINERO REAL');
            $this->assertTrue(mb_check_encoding($respuesta->getContent(),'UTF-8'));
            if ($directorio) {
                // Vista estática: assets locales. El navegador no accede a Laravel ni a una BD.
                $base = 'file:///'.str_replace('\\','/',public_path()).'/';
                $html = preg_replace_callback('~(href|src)="http://localhost/((?:css/|images/|js-)[^"]+)"~',
                    fn ($m) => $m[1].'="'.$base.$m[2].'"', $respuesta->getContent());
                file_put_contents($directorio.DIRECTORY_SEPARATOR.$nombre.'.html',$html);
            }
        };
        $p = $this->pedido();
        $guardar('preparacion',$this->get(route('admin.pagos.crear',$p->id)));
        $qr = $this->iniciar($p);
        $guardar('qr-pendiente',$this->get(route('admin.pagos.show',$qr->referencia)));
        $tarjeta = $this->iniciar($this->pedido(),'TARJETA_SIMULADA');
        $guardar('tarjeta-pendiente',$this->get(route('admin.pagos.show',$tarjeta->referencia)));
        $this->escenario($tarjeta,'TARJETA_DEMO_APROBADA')->assertRedirect();
        $guardar('tarjeta-aprobada',$this->get(route('admin.pagos.show',$tarjeta->referencia)));
        $this->escenario($qr,'QR_DEMO_APROBADO')->assertRedirect();
        $guardar('qr-aprobado',$this->get(route('admin.pagos.show',$qr->referencia)));
        $rechazada = $this->iniciar($this->pedido(),'TARJETA_SIMULADA');
        $this->escenario($rechazada,'TARJETA_DEMO_RECHAZADA')->assertRedirect();
        $guardar('tarjeta-rechazada',$this->get(route('admin.pagos.show',$rechazada->referencia)));
        $pendiente3ds = $this->iniciar($this->pedido(),'TARJETA_SIMULADA');
        $this->escenario($pendiente3ds,'TARJETA_DEMO_3DS_PENDIENTE')->assertRedirect();
        $guardar('tarjeta-3ds',$this->get(route('admin.pagos.show',$pendiente3ds->referencia)));
        $guardar('recuperacion',$this->get(route('admin.pagos.recuperar',['clave_idempotencia'=>(string)Str::uuid()])),409);
        $guardar('historial',$this->get(route('admin.pagos.historial')));
    }
}
