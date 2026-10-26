<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Caja\{ConsolidacionCajaService, CoordinadorCobrosCaja, TotalesCajaPeriodo};
use App\Support\ImporteConsolidadoVista;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class ConsolidadosCajaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) throw new LogicException('Solo SQLite en memoria.');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach (DB::select('PRAGMA database_list') as $base) $this->assertSame('', $base->file);
        foreach (glob(database_path('migrations/*.php')) as $archivo) (require $archivo)->up();
        DB::table('roles')->insert(['rol_id'=>1, 'nombre'=>'Consulta sintética', 'estado'=>1]);
        DB::table('usuarios')->insert(['id'=>1,'rol_id'=>1,'nombreCompleto'=>'Actor sintético','nombreUsuario'=>'consolidacion', 'password'=>'fixture','estado'=>1]);
        $permiso = DB::table('permisos')->insertGetId(['nombre'=>'consultar_caja']);
        DB::table('rol_permisos')->insert(['idRol'=>1,'idPermiso'=>$permiso]);
        $this->actingAs(User::findOrFail(1));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-26 12:00:00', 'America/La_Paz'));
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }

    private function cierre(string $fecha, string $total = '10.10', ?string $contado = null, int $numero = 1, ?int $cierre = null): int
    {
        $cierre ??= DB::table('cierre_cajas')->insertGetId(['idUsuario'=>1,'fecha_operativa'=>$fecha,'fechaInicio'=>$fecha.' 04:00:00']);
        $version = DB::table('cierre_caja_versiones')->insertGetId(['cierre_caja_id'=>$cierre,'numero_version'=>$numero,
            'responsable_id'=>1,'registrado_en'=>'2026-10-26 10:00:00','total_ventas'=>$total,
            'efectivo_esperado'=>$total,'qr_registrado'=>'0.00','efectivo_contado'=>$contado ?? $total,
            'observacion'=>'<script>NO_HTML()</script>', 'motivo_correccion'=>$numero > 1 ? 'Recuento' : null]);
        if ($total !== '0.00') {
            $venta = DB::table('cierre_caja_version_ventas as d')->join('cierre_caja_versiones as v','v.id','=','d.cierre_caja_version_id')
                ->where('v.cierre_caja_id',$cierre)->value('venta_id');
            if (!$venta) {
                $pedido = DB::table('pedidos')->insertGetId(['usuario_id'=>1,'fechaHora'=>$fecha.' 16:00:00','estado'=>'Pagado','total'=>$total]);
                $venta = DB::table('ventas')->insertGetId(['pedido_id'=>$pedido,'usuario_id'=>1,'fecha_hora'=>$fecha.' 16:00:00',
                    'estado'=>'Completado','metodo_pago'=>'Efectivo','total'=>$total,'monto_efectivo'=>$total,'monto_qr'=>'0.00','monto_recibido'=>$total,'cambio'=>'0.00']);
            }
            DB::table('cierre_caja_version_ventas')->insert(['cierre_caja_version_id'=>$version,'venta_id'=>$venta,
                'fecha_cobro'=>$fecha.' 16:00:00','metodo_pago'=>'Efectivo','total'=>$total,'monto_efectivo'=>$total,'monto_qr'=>'0.00']);
        }
        return $cierre;
    }

    public function test_mes_y_anio_sin_cierres_no_suponen_ventas_cero_para_los_dias_pendientes(): void
    {
        $servicio = new ConsolidacionCajaService;
        $mes = $servicio->mensual(2024, 2);
        $this->assertSame('incompleto',$mes['estado']);
        $this->assertCount(29,$mes['fechas_sin_cierre']);
        $this->assertNull($mes['dias'][0]['totales']);
        $this->assertSame('0.00',$mes['totales']['total_ventas']);
        $anio = $servicio->anual(2024);
        $this->assertCount(366,$anio['fechas_sin_cierre']);
        $this->assertSame('sin_cierres',$anio['conciliacion']);
        $this->get('/admin/caja/mensual?anio=2024&mes=2')->assertOk()->assertSee('No hay cierres válidos')->assertSee('no se estiman como ventas cero');
    }

    public function test_fechas_futuras_y_dia_abierto_no_se_exigen_como_cierres_pendientes(): void
    {
        $servicio = new ConsolidacionCajaService;
        $mes = $servicio->mensual(2026,10);
        $this->assertSame(25,$mes['dias_exigibles']);
        $this->assertSame(1,$mes['dias_abiertos']);
        $this->assertSame(5,$mes['dias_futuros']);
        $this->assertCount(25,$mes['fechas_sin_cierre']);
        $this->assertSame('abierto',$mes['dias'][25]['estado']);
        $futuro = $servicio->mensual(2026,11);
        $this->assertSame('futuro',$futuro['estado']);
        $this->assertSame([],$futuro['fechas_sin_cierre']);
        $this->assertSame('futuro',$servicio->anual(2027)['estado']);
        $this->assertSame('incompleto',$servicio->anual(2026)['estado']);
    }

    public function test_rectificaciones_consolidan_solo_version_vigente_y_no_compensan_silenciosamente_diferencias(): void
    {
        $cierre = $this->cierre('2026-10-01');
        $this->cierre('2026-10-01','10.10','11.11',2,$cierre);
        $this->cierre('2026-10-01','10.10','9.09',3,$cierre);
        $this->cierre('2026-10-02','10.10','11.11');
        $servicio = new ConsolidacionCajaService;
        $mes = $servicio->mensual(2026,10);
        $this->assertNotNull($mes['totales']);
        $this->assertSame('20.20',$mes['totales']['total_ventas']);
        $this->assertSame('20.20',$mes['totales']['efectivo_contado']);
        $this->assertSame('0.00',$mes['totales']['diferencia']);
        $this->assertSame('-1.01',$mes['totales']['faltantes']);
        $this->assertSame('1.01',$mes['totales']['sobrantes']);
        $this->assertSame('con_diferencias',$mes['conciliacion']);
        $this->assertSame(2,$mes['dias_con_diferencia']);
        $this->assertSame(1,$mes['dias_rectificados']);
        $this->assertSame(3,$mes['dias'][0]['numero_version']);
        $this->assertSame(2,$mes['totales']['cantidad_ventas']);
        $this->assertSame($mes['totales'],$servicio->anual(2026)['totales']);
        $this->assertSame(4,DB::table('cierre_caja_versiones')->count());
        $this->get('/admin/caja/mensual?anio=2026&mes=10')->assertOk()->assertSee('rectificado')->assertSee('Bs -1,01')->assertSee('Con diferencias financieras');
    }

    public function test_un_mes_completo_con_dias_sin_ventas_requiere_cierres_explicitos_cero(): void
    {
        for ($dia=1;$dia<=28;$dia++) $this->cierre(sprintf('2025-02-%02d',$dia),'0.00');
        $mes = (new ConsolidacionCajaService)->mensual(2025,2);
        $this->assertSame('completo',$mes['estado']);
        $this->assertSame('conciliado',$mes['conciliacion']);
        $this->assertSame(28,$mes['dias_cerrados']);
        $this->assertSame([],$mes['fechas_sin_cierre']);
        $this->assertSame('0.00',$mes['totales']['total_ventas']);
        $this->assertSame(0,$mes['totales']['cantidad_ventas']);
    }

    public function test_anio_completo_bisiesto_y_consistencia_mensual_anual_en_una_sola_lectura(): void
    {
        for ($dia=CarbonImmutable::parse('2024-01-01');$dia->year === 2024;$dia=$dia->addDay()) $this->cierre($dia->toDateString(),'0.00');
        DB::enableQueryLog(); DB::flushQueryLog();
        $anio = (new ConsolidacionCajaService)->anual(2024);
        $financieras = array_filter(DB::getQueryLog(),fn($q)=>str_contains($q['query'],'from "cierre_cajas" as "c"'));
        $this->assertCount(1,$financieras);
        $this->assertSame('completo',$anio['estado']);
        $this->assertSame('conciliado',$anio['conciliacion']);
        $this->assertSame(366,$anio['dias_cerrados']);
        $sumado = TotalesCajaPeriodo::cero();
        foreach ($anio['meses'] as $mes) {
            $this->assertSame('completo',$mes['estado']);
            $sumado = TotalesCajaPeriodo::sumar($sumado,$mes['totales']);
        }
        $this->assertSame($sumado,$anio['totales']);
    }

    public function test_historicos_sin_fecha_no_se_incluyen_y_no_se_leen_ventas_actuales(): void
    {
        $id = $this->cierre('2026-10-01');
        DB::table('cierre_cajas')->where('id',$id)->update(['fecha_operativa'=>null]);
        $this->cierre('2026-10-02');
        DB::table('ventas')->update(['total'=>'999.99']);
        DB::enableQueryLog(); DB::flushQueryLog();
        $mes = (new ConsolidacionCajaService)->mensual(2026,10);
        $this->assertSame('10.10',$mes['totales']['total_ventas']);
        $sql = strtolower(implode("\n",array_column(DB::getQueryLog(),'query')));
        $this->assertStringNotContainsString('from "ventas"',$sql);
        $this->assertStringNotContainsString('from "pedidos"',$sql);
        $this->assertSame(1,$mes['dias_cerrados']);
    }

    public function test_importes_grandes_superan_el_limite_diario_sin_perder_precision(): void
    {
        $this->cierre('2026-10-01','90000000000000.00');
        $this->cierre('2026-10-02','90000000000000.00');
        $mes = (new ConsolidacionCajaService)->mensual(2026,10);
        $this->assertSame('180000000000000.00',$mes['totales']['total_ventas']);
        $this->get('/admin/caja/anual?anio=2026')->assertOk()->assertSee('Bs 180.000.000.000.000,00');
        // SQLite no preserva todos los decimales grandes: el extremo con centavos se comprueba como cadenas exactas.
        $fila = (object)['total_ventas'=>'99999999999999.99','efectivo_esperado'=>'99999999999999.99',
            'qr_registrado'=>'0.00','efectivo_contado'=>'99999999999999.98','diferencia'=>'-0.01','cantidad_ventas'=>1];
        $total = TotalesCajaPeriodo::cero();
        for ($i=0;$i<366;$i++) $total = TotalesCajaPeriodo::sumar($total,TotalesCajaPeriodo::version($fila));
        $this->assertSame('36599999999999996.34',$total['total_ventas']);
        $this->assertSame('-3.66',$total['diferencia']);
        $this->assertSame('Bs 36.599.999.999.999.996,34',ImporteConsolidadoVista::formatear($total['total_ventas']));
    }

    public function test_falta_de_version_o_importes_incoherentes_no_producen_totales_enganosos(): void
    {
        $id = DB::table('cierre_cajas')->insertGetId(['idUsuario'=>1,'fecha_operativa'=>'2026-10-01','fechaInicio'=>'2026-10-01 04:00:00']);
        $servicio = new ConsolidacionCajaService;
        $this->assertNull($servicio->mensual(2026,10)['totales']);
        $this->assertNull($servicio->anual(2026)['totales']);
        $this->get('/admin/caja/mensual?anio=2026&mes=10')->assertOk()->assertSee('Totales no disponibles')->assertSee('no se han reemplazado importes por cero');
        $this->cierre('2026-10-01','10.10',null,1,$id);
        DB::table('cierre_caja_versiones')->where('cierre_caja_id',$id)->update(['qr_registrado'=>'1.00']);
        $this->assertSame('inconsistente',$servicio->mensual(2026,10)['dias'][0]['estado']);
        $this->assertNull($servicio->mensual(2026,10)['totales']);
    }

    public function test_cierre_ilegal_del_dia_abierto_no_se_suma_como_oficial_valido(): void
    {
        $this->cierre('2026-10-26');
        $mes = (new ConsolidacionCajaService)->mensual(2026,10);
        $this->assertNull($mes['totales']);
        $this->assertSame(['2026-10-26'],$mes['fechas_inconsistentes']);
    }

    public function test_fechas_operativas_bolivia_y_limites_de_mes_no_usan_fecha_de_registro(): void
    {
        $this->cierre('2026-09-30'); $this->cierre('2026-10-01');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-01 02:00:00','UTC'));
        $respuesta = $this->get('/admin/caja/mensual')->assertOk()->assertViewHas('mes',10);
        $this->assertSame('2026-10-31',$respuesta->viewData('resumen')['fecha_corte']);
        $this->assertSame('10.10',$respuesta->viewData('resumen')['totales']['total_ventas']);
        $this->assertSame('10.10',(new ConsolidacionCajaService)->mensual(2026,9)['totales']['total_ventas']);
    }

    public function test_validacion_periodos_y_salida_segura(): void
    {
        foreach (['anio=999','anio=10000','anio[]=2026','anio=2026.5'] as $query) $this->get('/admin/caja/anual?'.$query)->assertSessionHasErrors('anio');
        foreach (['mes=0','mes=13','mes[]=1'] as $query) $this->get('/admin/caja/mensual?'.$query)->assertSessionHasErrors('mes');
        try { (new ConsolidacionCajaService)->mensual(2026,13); $this->fail('Mes inválido.'); }
        catch (ValidationException) { $this->assertTrue(true); }
        $this->cierre('2026-10-01');
        $this->get('/admin/caja/mensual?anio=2026&mes=10')->assertOk()->assertDontSee('NO_HTML')->assertHeader('Cache-Control','no-store, private');
    }

    public function test_permisos_roles_inactivos_e_invitados_no_leen_informacion_financiera(): void
    {
        DB::table('rol_permisos')->delete();
        DB::enableQueryLog(); DB::flushQueryLog();
        foreach (['/admin/caja/mensual','/admin/caja/anual'] as $url) $this->get($url)->assertForbidden();
        try { (new ConsolidacionCajaService)->anual(2026); $this->fail('Falta autorización.'); }
        catch (AuthorizationException) { $this->assertTrue(true); }
        $sql = implode("\n",array_column(DB::getQueryLog(),'query'));
        $this->assertStringNotContainsString('cierre_cajas',$sql);
        Auth::logout(); $this->get('/admin/caja/mensual')->assertRedirect(route('login'));
    }

    public function test_usuario_y_rol_inactivos_no_acceden(): void
    {
        DB::table('roles')->update(['estado'=>0]); $this->actingAs(User::findOrFail(1));
        $this->get('/admin/caja/mensual')->assertForbidden(); $this->get('/admin/caja/anual')->assertForbidden();
        DB::table('roles')->update(['estado'=>1]); DB::table('usuarios')->update(['estado'=>0]);
        $this->actingAs(User::findOrFail(1)); $this->get('/admin/caja/anual')->assertRedirect(route('login'));
    }

    public function test_consultas_sin_escrituras_y_dos_rutas_get_dentro_de_p4(): void
    {
        $this->cierre('2026-10-01');
        $tablas=['cierre_cajas','cierre_caja_versiones','cierre_caja_version_ventas','ventas','pedidos','bitacoras','stocks'];
        $antes=[]; foreach ($tablas as $tabla) $antes[$tabla]=DB::table($tabla)->orderBy('id')->get()->toJson();
        DB::enableQueryLog(); DB::flushQueryLog();
        foreach (['/admin/caja/mensual?anio=2026&mes=10','/admin/caja/anual?anio=2026'] as $url) $this->get($url)->assertOk();
        foreach (DB::getQueryLog() as $q) {
            $this->assertDoesNotMatchRegularExpression('/\A\s*(insert|update|delete|create|alter|drop|begin|commit)\b/i',$q['query']);
            $this->assertStringNotContainsString('GET_LOCK',$q['query']);
        }
        foreach ($antes as $tabla=>$json) $this->assertSame($json,DB::table($tabla)->orderBy('id')->get()->toJson());
        foreach (['admin.caja.mensual','admin.caja.anual'] as $nombre) {
            $ruta=app('router')->getRoutes()->getByName($nombre);
            $this->assertSame(['GET','HEAD'],$ruta->methods());
            $this->assertContains('auth',$ruta->gatherMiddleware());
            $this->assertContains('permiso:consultar_caja',$ruta->gatherMiddleware());
        }
        $this->get('/admin/caja/anual')->assertOk()->assertSee('Pedidos, Ventas y Caja')->assertSee('Consolidado mensual de caja');
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
    }

    public function test_efectivo_qr_y_mixto_persistidos_se_suman_sin_duplicar_el_total(): void
    {
        $mixto=$this->cierre('2026-10-01','15.00');
        $qr=$this->cierre('2026-10-02','7.25');
        DB::table('cierre_caja_versiones')->where('cierre_caja_id',$mixto)->update(['efectivo_esperado'=>'10.00','qr_registrado'=>'5.00','efectivo_contado'=>'10.00']);
        DB::table('cierre_caja_versiones')->where('cierre_caja_id',$qr)->update(['efectivo_esperado'=>'0.00','qr_registrado'=>'7.25','efectivo_contado'=>'0.00']);
        $mes=(new ConsolidacionCajaService)->mensual(2026,10);
        $this->assertSame('22.25',$mes['totales']['total_ventas']);
        $this->assertSame('10.00',$mes['totales']['efectivo_esperado']);
        $this->assertSame('12.25',$mes['totales']['qr_registrado']);
        $this->assertSame('10.00',$mes['totales']['efectivo_contado']);
        $this->assertSame('0.00',$mes['totales']['diferencia']);
    }

    public function test_consistencia_diaria_mensual_anual_y_limite_de_anio_con_importes_reales(): void
    {
        $this->cierre('2025-12-31','1.23'); $this->cierre('2026-01-01','2.34');
        $this->cierre('2026-09-30','3.45'); $this->cierre('2026-10-01','4.56');
        $servicio=new ConsolidacionCajaService;
        $anio=$servicio->anual(2026);
        $this->assertSame('10.35',$anio['totales']['total_ventas']);
        foreach ($anio['meses'] as $mes) {
            $diario=TotalesCajaPeriodo::cero();
            foreach ($mes['dias'] as $dia) if ($dia['totales']) $diario=TotalesCajaPeriodo::sumar($diario,$dia['totales']);
            $this->assertSame($diario,$mes['totales']);
            $this->assertSame($mes['totales'],$servicio->mensual(2026,$mes['mes'])['totales']);
        }
        $this->assertSame('1.23',$servicio->anual(2025)['totales']['total_ventas']);
    }

    public function test_errores_internos_de_consulta_no_exponen_sql_o_trazas(): void
    {
        config(['app.debug'=>true]);
        DB::connection()->beforeExecuting(static function ($sql) {
            if (str_contains($sql,'cierre_cajas')) throw new \RuntimeException('SQLSTATE password=SECRETO');
        });
        foreach (['/admin/caja/mensual','/admin/caja/anual'] as $url) $this->get($url)->assertStatus(503)
            ->assertSee('Consulta de caja no disponible')->assertDontSee('SQLSTATE')->assertDontSee('SECRETO');
    }

    public function test_exportacion_opcional_de_consolidados_para_revision_visual_aislada(): void
    {
        $this->cierre('2026-10-01','10.10','9.09');
        $directorio=getenv('KARMA_CAJA_HTML_DIR');
        if ($directorio) {
            $real=realpath($directorio); $temporal=realpath(sys_get_temp_dir());
            if (!$real || !$temporal || !str_starts_with(strtolower($real.DIRECTORY_SEPARATOR),strtolower($temporal.DIRECTORY_SEPARATOR))) throw new LogicException('Solo exportación temporal.');
        }
        foreach (['mensual'=>'/admin/caja/mensual?anio=2026&mes=10','anual'=>'/admin/caja/anual?anio=2026'] as $nombre=>$url) {
            $respuesta=$this->get($url)->assertOk()->assertSee('Bs -1,01')->assertDontSee('data-borrador',false);
            if ($directorio) file_put_contents($real.DIRECTORY_SEPARATOR.$nombre.'.html',$respuesta->getContent());
        }
    }
}
