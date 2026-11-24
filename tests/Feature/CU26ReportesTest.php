<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{Pedido, Producto, Stock, User, Venta};
use App\Services\Caja\{CierreDiarioService, CoordinacionCobrosCaja, CoordinadorCobrosCaja, ConsolidacionCajaService, ReconciliacionCajaService, RectificacionCierreDiarioService};
use App\Services\Pagos\{EscenarioSimulado, FinalizarCobroService, IntentosPagoService};
use App\Services\Reportes\VentasReporteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class CU26ReportesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new LogicException('Reportes CU26 solo sobre SQLite en memoria.');
        }
        foreach (DB::select('PRAGMA database_list') as $base) $this->assertSame('', $base->file);
        foreach (glob(database_path('migrations/*.php')) as $archivo) (require $archivo)->up();
        DB::table('roles')->insert(['rol_id'=>1,'nombre'=>'Operador ficticio CU26','estado'=>1]);
        DB::table('usuarios')->insert(['id'=>1,'rol_id'=>1,'nombreCompleto'=>'Prueba de reportes',
            'nombreUsuario'=>'reportes-cu26','password'=>'fixture-no-real','estado'=>1]);
        foreach (['ver_reportes','cobrar_ventas','consultar_caja','confirmar_cierre_caja','rectificar_cierre_caja','consultar_bitacora','consultar_historial_pedidos'] as $nombre) {
            $id=DB::table('permisos')->insertGetId(['nombre'=>$nombre]);
            DB::table('rol_permisos')->insert(['idRol'=>1,'idPermiso'=>$id]);
        }
        $this->actingAs(User::findOrFail(1));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23 12:00:00','America/La_Paz'));
        DB::table('categorias')->insert(['id'=>1,'nombre'=>'Prueba','estado'=>1]);
        Producto::create(['nombre'=>'Café ficticio','precio'=>'12.35','disponible'=>true,'categoria_id'=>1]);
        Stock::create(['producto_id'=>1,'stock_actual'=>100,'stock_minimo'=>0]);
        config(['pagos.simulacion.activa'=>true,'pagos.simulacion.resultado'=>'PENDIENTE']);
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }

    private function pedido(): Pedido
    {
        return DB::transaction(function () {
            $p=Pedido::create(['usuario_id'=>1,'fechaHora'=>now(),'estado'=>'Registrado','total'=>'24.70','subtotal'=>'24.70','descuento'=>'0.00']);
            $p->detalles()->create(['producto_id'=>1,'cantidad'=>2,'precioUnitario'=>'12.35','subtotal'=>'24.70','opcion'=>'']);
            app(\App\Services\ReservaPedidoService::class)->mover($p);
            return $p->fresh();
        });
    }

    private function simulado(string $medio, ?EscenarioSimulado $escenario = null)
    {
        $p=$this->pedido();$servicio=app(IntentosPagoService::class);
        $t=$servicio->crear($p->id,$medio,$p->versionCobro(),(string)Str::uuid());
        return $escenario ? $servicio->simular($t->id,$escenario) : $t;
    }

    private function cincoVentas(): void
    {
        foreach (['Efectivo','QR','Mixto'] as $metodo) {
            $p=$this->pedido();
            app(FinalizarCobroService::class)->presencial($p->id,['version_pedido'=>$p->versionCobro(),
                'metodo_pago'=>$metodo,'monto_recibido'=>'30.00','monto_qr'=>'10.00']);
        }
        $this->simulado('QR_SIMULADO',EscenarioSimulado::QR_APROBADO);
        $this->simulado('TARJETA_SIMULADA',EscenarioSimulado::TARJETA_APROBADA);
    }

    public function test_reporte_incluye_cinco_metodos_y_mixto_una_sola_vez(): void
    {
        $this->cincoVentas();
        $r=$this->get('/admin/reportes/metodos-pago')->assertOk()->assertSee('QR manual')->assertSee('Mixto')
            ->assertSee('QR simulado')->assertSee('Tarjeta simulada')->assertSee('SIMULACIÓN LOCAL — SIN DINERO REAL');
        $this->assertSame(['total'=>'123.50','monto_efectivo'=>'39.40','monto_qr'=>'59.40','monto_tarjeta'=>'24.70'],$r->viewData('totales'));
        $resumen=$r->viewData('resumen');
        $this->assertSame(5,$resumen->sum('cantidad'));
        $this->assertSame('24.70',$resumen['Mixto']['total']);
        $this->assertSame('14.70',$resumen['Mixto']['monto_efectivo']);
        $this->assertSame('10.00',$resumen['Mixto']['monto_qr']);
        $this->get('/admin/reportes/ventas-diarias')->assertOk()->assertViewHas('total','123.50')->assertViewHas('cantidad',5);
        $this->get('/home')->assertOk()->assertSee('123.50')->assertSee('SIMULACIÓN LOCAL — SIN DINERO REAL');
        $this->assertSame(90,(int)Stock::first()->stock_actual);
    }

    public function test_rechazado_pendiente_expirado_y_cancelado_no_son_ventas(): void
    {
        $this->cincoVentas();
        $this->simulado('TARJETA_SIMULADA',EscenarioSimulado::TARJETA_RECHAZADA);
        $this->simulado('TARJETA_SIMULADA',EscenarioSimulado::TARJETA_3DS);
        $this->simulado('QR_SIMULADO',EscenarioSimulado::EXPIRACION);
        $t=$this->simulado('QR_SIMULADO');app(IntentosPagoService::class)->cancelar($t->id);
        $this->get('/admin/reportes/ventas-diarias')->assertOk()->assertViewHas('cantidad',5)->assertViewHas('total','123.50');
        $this->assertSame(5,Venta::count());
    }

    public function test_importes_exactos_y_presentacion_grande_sin_float(): void
    {
        $ventas=collect();
        foreach (range(1,1000) as $n) $ventas->push((object)['total'=>'0.10','monto_efectivo'=>'0.10','monto_qr'=>'0.00','monto_tarjeta'=>'0.00','metodo_pago'=>'Efectivo','origen'=>'OPERACION']);
        $r=app(VentasReporteService::class)->resumir($ventas);
        $this->assertSame('100.00',$r['totales']['total']);
        $this->assertSame('Bs 180,000,000,000,000.01',\App\Support\ImporteReporteVista::formatear('180000000000000.01'));
    }

    public function test_historico_transferencia_qr_se_conserva_y_no_suma_cambio(): void
    {
        $p=$this->pedido();
        $v=app(FinalizarCobroService::class)->presencial($p->id,['version_pedido'=>$p->versionCobro(),'metodo_pago'=>'QR']);
        DB::table('ventas')->where('id',$v->id)->update(['metodo_pago'=>'Transferencia QR']);
        $antes=DB::table('ventas')->first();
        $r=$this->get('/admin/reportes/metodos-pago')->assertOk()->assertSee('QR manual');
        $this->assertSame('24.70',$r->viewData('resumen')['QR']['total']);
        $this->assertEquals($antes,DB::table('ventas')->first());
    }

    public function test_limites_del_dia_boliviano_y_filtros(): void
    {
        $this->cincoVentas();
        DB::table('ventas')->update(['fecha_hora'=>'2026-11-24 03:59:59']);
        DB::table('ventas')->where('id',1)->update(['fecha_hora'=>'2026-11-24 04:00:00']);
        $this->get('/admin/reportes/ventas-diarias?fecha=2026-11-23')->assertOk()->assertViewHas('cantidad',4)->assertViewHas('total','98.80');
        $this->get('/admin/reportes/ventas-diarias?fecha=2026-11-24')->assertViewHas('cantidad',1)->assertViewHas('total','24.70');
        $this->get('/admin/reportes/metodos-pago?desde=2026-11-24&hasta=2026-11-23')->assertSessionHasErrors('hasta');
    }

    public function test_componentes_inconsistentes_no_muestran_totales_parciales(): void
    {
        $this->cincoVentas();
        DB::table('ventas')->where('id',1)->update(['monto_efectivo'=>'1.00']);
        foreach (['/admin/reportes/ventas-diarias','/admin/reportes/metodos-pago'] as $url) {
            $this->get($url)->assertStatus(422)->assertSee('No se muestran totales parciales')->assertDontSee('123.50');
        }
    }

    public function test_simulacion_fuera_de_contexto_bloquea_reportes_listados_y_detalle(): void
    {
        $this->cincoVentas();$id=Venta::where('metodo_pago','TARJETA_SIMULADA')->value('pedido_id');
        config(['pagos.simulacion.activa'=>false]);
        foreach (['/admin/reportes/ventas-diarias','/admin/reportes/metodos-pago','/admin/ventas','/admin/pedidos','/admin/pedidos/ver/'.$id,'/home'] as $url) {
            $this->get($url)->assertStatus(503)->assertDontSee('123.50');
        }
        $this->assertSame(5,Venta::count());
    }

    public function test_solo_datos_presenciales_siguen_visibles_con_simulacion_desactivada(): void
    {
        $p=$this->pedido();app(FinalizarCobroService::class)->presencial($p->id,['version_pedido'=>$p->versionCobro(),'metodo_pago'=>'Efectivo','monto_recibido'=>'30.00']);
        config(['pagos.simulacion.activa'=>false]);
        $this->get('/admin/reportes/ventas-diarias')->assertOk()->assertSee('Presencial')->assertViewHas('total','24.70')->assertDontSee('SIMULACIÓN LOCAL — SIN DINERO REAL');
    }

    public function test_permiso_de_cobro_no_otorga_automaticamente_reportes(): void
    {
        $id=DB::table('permisos')->where('nombre','ver_reportes')->value('id');
        DB::table('rol_permisos')->where('idPermiso',$id)->delete();
        $this->get('/admin/reportes/metodos-pago')->assertForbidden();
        $this->get('/admin/reportes/ventas-diarias')->assertForbidden();
        $this->get('/admin/ventas')->assertOk();
    }

    private function coordinacionDePrueba(): CoordinacionCobrosCaja
    {
        return new class implements CoordinacionCobrosCaja {
            public function exclusivo(\Closure $operacion): mixed { return (new CoordinadorCobrosCaja)->exclusivo($operacion); }
            public function permiteCierreDefinitivo(): bool { return true; }
        };
    }

    public function test_caja_rectificacion_conciliacion_y_consolidados_preservan_tarjeta_sin_duplicar(): void
    {
        $this->cincoVentas();$this->travel(1)->days();$coordinacion=$this->coordinacionDePrueba();
        $v=(new CierreDiarioService($coordinacion))->confirmar('2026-11-23','39.40');
        $this->assertSame('123.50',$v->total_ventas);$this->assertSame('24.70',$v->tarjeta_registrado);
        $nueva=(new RectificacionCierreDiarioService($coordinacion))->rectificar($v->cierre_caja_id,1,'39.40','Revisión ficticia','Observación de demostración');
        $this->assertSame('24.70',$nueva->tarjeta_registrado);
        $this->assertSame(5,$nueva->detalles->count());
        $this->assertSame('24.70',$v->fresh()->tarjeta_registrado);
        $this->assertSame('CONFIRMACION_OBSERVADA',app(ReconciliacionCajaService::class)->consultar('2026-11-23')['estado']);
        $this->assertSame('123.50',app(ConsolidacionCajaService::class)->mensual(2026,11)['totales']['total_ventas']);
        $this->assertSame('24.70',app(ConsolidacionCajaService::class)->anual(2026)['totales']['tarjeta_registrado']);
        $this->get('/admin/caja/cierres/'.$v->cierre_caja_id)->assertOk()->assertSee('Tarjeta registrada')->assertSee('SIMULACION_LOCAL');
        $this->get('/admin/caja/previsualizacion?fecha=2026-11-23')->assertOk()->assertSee('Tarjeta registrada');
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
        config(['pagos.simulacion.activa'=>false]);
        foreach (['/admin/caja','/admin/caja/cierres/'.$v->cierre_caja_id,'/admin/caja/mensual?anio=2026&mes=11','/admin/caja/anual?anio=2026','/admin/caja/previsualizacion?fecha=2026-11-23'] as $url) {
            $this->get($url)->assertStatus(503);
        }
    }

    public function test_exportacion_sintetica_de_reportes_y_caja_para_revision_visual(): void
    {
        $this->cincoVentas();
        $vistas=['reportes-pagos'=>'/admin/reportes/metodos-pago','reportes-diarias'=>'/admin/reportes/ventas-diarias'];
        $this->travel(1)->days();
        $version=(new CierreDiarioService($this->coordinacionDePrueba()))->confirmar('2026-11-23','39.40');
        $vistas['reportes-pagos'].='?desde=2026-11-23&hasta=2026-11-23';
        $vistas['reportes-diarias'].='?fecha=2026-11-23';
        $vistas += ['caja-diaria'=>'/admin/caja/previsualizacion?fecha=2026-11-23',
            'caja-mensual'=>'/admin/caja/mensual?anio=2026&mes=11', 'caja-anual'=>'/admin/caja/anual?anio=2026',
            'caja-version'=>'/admin/caja/cierres/'.$version->cierre_caja_id];
        $directorio=getenv('CU26_VISTAS_DIR');
        if ($directorio) {
            $real=realpath($directorio);$temporal=realpath(sys_get_temp_dir());
            if (!$real || !$temporal || !str_starts_with(strtolower($real.DIRECTORY_SEPARATOR),strtolower($temporal.DIRECTORY_SEPARATOR))) throw new LogicException('Exportación solo al temporal verificado.');
        }
        foreach ($vistas as $nombre=>$ruta) {
            $r=$this->get($ruta)->assertOk()->assertSee('SIMULACIÓN LOCAL — SIN DINERO REAL');
            $this->assertTrue(mb_check_encoding($r->getContent(),'UTF-8'),'La vista debe conservar UTF-8.');
            if ($directorio) {
                $base='file:///'.str_replace('\\','/',public_path()).'/';
                $html=preg_replace_callback('~(href|src)="http://localhost/((?:css/|images/|js-)[^"]+)"~',fn($m)=>$m[1].'="'.$base.$m[2].'"',$r->getContent());
                file_put_contents($directorio.DIRECTORY_SEPARATOR.$nombre.'.html',$html);
            }
        }
    }
}
