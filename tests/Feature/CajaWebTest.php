<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Caja\CoordinadorCobrosCaja;
use App\Support\ImporteCajaVista;
use Illuminate\Support\Facades\{Auth, DB};
use LogicException;
use Tests\TestCase;

class CajaWebTest extends TestCase
{
    private int $cierre;
    private int $v1;
    private int $v2;
    private int $otro;
    private int $historico;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) throw new LogicException('Solo SQLite en memoria.');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach (DB::select('PRAGMA database_list') as $base) $this->assertSame('', $base->file);
        foreach (glob(database_path('migrations/*.php')) as $archivo) (require $archivo)->up();
        DB::table('roles')->insert(['rol_id' => 1, 'nombre' => 'Consulta explícita', 'estado' => 1]);
        DB::table('usuarios')->insert(['id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Actor sintético caja',
            'nombreUsuario' => 'consulta-caja', 'password' => 'fixture', 'estado' => 1]);
        $permiso = DB::table('permisos')->insertGetId(['nombre' => 'consultar_caja']);
        DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $permiso]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 02:30:00', 'UTC'));
        $this->actingAs(User::findOrFail(1));
        $pedido = DB::table('pedidos')->insertGetId(['usuario_id' => 1, 'fechaHora' => '2026-10-06 20:00:00',
            'total' => '10.10', 'estado' => 'Pagado']);
        $venta = DB::table('ventas')->insertGetId(['pedido_id' => $pedido, 'usuario_id' => 1,
            'fecha_hora' => '2026-10-07 02:00:00', 'total' => '10.10', 'monto_efectivo' => '10.10',
            'monto_qr' => '0.00', 'monto_recibido' => '10.10', 'cambio' => '0.00',
            'metodo_pago' => 'Efectivo', 'estado' => 'Completado']);
        $this->cierre = $this->cabecera('2026-10-05');
        $this->v1 = $this->versionFixture($this->cierre, 1, '10.10');
        $this->v2 = $this->versionFixture($this->cierre, 2, '9.09');
        $pedidoHistorico = DB::table('pedidos')->insertGetId(['usuario_id' => 1, 'fechaHora' => '2026-10-06 02:00:00',
            'total' => '10.10', 'estado' => 'Pagado']);
        // Una venta histórica diferente: la previsualización del día abierto no debe pertenecer a otro cierre.
        $venta = DB::table('ventas')->insertGetId(['pedido_id' => $pedidoHistorico, 'usuario_id' => 1,
            'fecha_hora' => '2026-10-06 02:00:00', 'total' => '10.10', 'monto_efectivo' => '10.10',
            'monto_qr' => '0.00', 'monto_recibido' => '10.10', 'cambio' => '0.00',
            'metodo_pago' => 'Efectivo', 'estado' => 'Anulado']);
        foreach ([$this->v1, $this->v2] as $v) {
            DB::table('cierre_caja_version_ventas')->insert(['cierre_caja_version_id' => $v, 'venta_id' => $venta,
                'fecha_cobro' => '2026-10-06 02:00:00', 'metodo_pago' => 'Método histórico',
                'total' => '10.10', 'monto_efectivo' => '10.10', 'monto_qr' => '0.00']);
        }
        $this->otro = $this->versionFixture($this->cabecera('2026-10-04'), 1, '10.10');
        $this->historico = $this->cabecera(null);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function cabecera(?string $fecha): int
    {
        return DB::table('cierre_cajas')->insertGetId(['idUsuario' => 1, 'fecha_operativa' => $fecha,
            'fechaInicio' => '2026-10-05 04:00:00', 'totalSistema' => '10.10',
            'totalDeclarado' => '10.10', 'diferencia' => '0.00', 'observacion' => '<script>legado()</script>']);
    }

    private function versionFixture(int $cierre, int $numero, string $contado): int
    {
        return DB::table('cierre_caja_versiones')->insertGetId(['cierre_caja_id' => $cierre, 'numero_version' => $numero,
            'responsable_id' => 1, 'registrado_en' => '2026-10-06 04:01:00', 'total_ventas' => '10.10',
            'efectivo_esperado' => '10.10', 'qr_registrado' => '0.00', 'efectivo_contado' => $contado,
            'observacion' => '<script>observacion()</script>', 'motivo_correccion' => '<img src=x onerror=motivo()>']);
    }

    private function urls(): array
    {
        return ['/admin/caja', '/admin/caja/previsualizacion?fecha=2026-10-06',
            '/admin/caja/cierres/'.$this->cierre,
            '/admin/caja/cierres/'.$this->cierre.'/versiones/'.$this->v1];
    }

    private function sinLecturasFinancieras(): void
    {
        $sql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        foreach (['cierre_cajas', 'cierre_caja_versiones', 'cierre_caja_version_ventas', 'from "ventas"'] as $tabla) {
            $this->assertStringNotContainsString($tabla, $sql);
        }
    }

    public function test_invitados_no_pueden_acceder_directamente_ni_cargar_modelos(): void
    {
        Auth::logout();
        DB::enableQueryLog(); DB::flushQueryLog();
        foreach ($this->urls() as $url) $this->get($url)->assertRedirect(route('login'));
        $this->sinLecturasFinancieras();
    }

    public function test_sin_permiso_ni_siquiera_consulta_cierres_inexistentes(): void
    {
        DB::table('rol_permisos')->delete();
        DB::enableQueryLog(); DB::flushQueryLog();
        foreach (array_merge($this->urls(), ['/admin/caja/cierres/999999/versiones/999999']) as $url) $this->get($url)->assertForbidden();
        $this->sinLecturasFinancieras();
    }

    public function test_rol_inactivo_rechaza_todas_las_consultas(): void
    {
        DB::table('roles')->update(['estado' => 0]);
        $this->actingAs(User::findOrFail(1));
        DB::enableQueryLog(); DB::flushQueryLog();
        foreach ($this->urls() as $url) $this->get($url)->assertForbidden();
        $this->sinLecturasFinancieras();
    }

    public function test_usuario_desactivado_no_consulta_finanzas(): void
    {
        DB::table('usuarios')->update(['estado' => 0]);
        $this->actingAs(User::findOrFail(1));
        DB::enableQueryLog(); DB::flushQueryLog();
        foreach ($this->urls() as $url) $this->get($url)->assertRedirect(route('login'));
        $this->sinLecturasFinancieras();
    }

    public function test_autorizacion_central_relee_el_estado_aunque_auth_tenga_rol_cacheado(): void
    {
        $this->assertTrue(Auth::user()->tienePermiso('consultar_caja'));
        DB::table('roles')->update(['estado' => 0]);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->get('/admin/caja/cierres/'.$this->cierre)->assertUnauthorized();
        $this->sinLecturasFinancieras();
    }

    public function test_listado_filtros_y_paginacion_distinguen_historicos(): void
    {
        $this->get('/admin/caja')->assertOk()->assertViewHas('cierres', fn ($p) => $p->total() === 2)
            ->assertSee('Bs -1,01')->assertSee('Las operaciones financieras de caja todavía no están habilitadas')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/admin/caja?desde=2026-10-05&hasta=2026-10-05')->assertOk()
            ->assertViewHas('cierres', fn ($p) => $p->total() === 1 && $p->first()->id === $this->cierre);
        $this->get('/admin/caja?tipo=historicos')->assertOk()->assertSee('Histórico no oficial')
            ->assertViewHas('cierres', fn ($p) => $p->total() === 1 && $p->first()->id === $this->historico);
        for ($i = 1; $i <= 12; $i++) $this->cabecera('2026-09-'.str_pad((string)$i, 2, '0', STR_PAD_LEFT));
        $this->get('/admin/caja?tipo=todos&por_pagina=10&page=2')->assertOk()
            ->assertViewHas('cierres', fn ($p) => $p->total() === 15 && $p->currentPage() === 2 && $p->count() === 5);
        $this->get('/admin/caja?desde=2020-01-01&hasta=2020-01-02')->assertOk()->assertSee('No hay cierres');
    }

    public function test_filtros_y_paginas_invalidos_se_rechazan(): void
    {
        foreach ([['desde=2026-02-30','desde'], ['desde=2026-10-06&hasta=2026-10-05','hasta'],
            ['tipo=otro','tipo'], ['page=0','page'], ['page[]=1','page'], ['por_pagina=99999','por_pagina'],
            ['tipo=historicos&desde=2026-10-01','tipo']] as [$query,$campo]) {
            $this->get('/admin/caja?'.$query)->assertSessionHasErrors($campo);
        }
        $this->get('/admin/caja/cierres/'.$this->cierre.'?ventas_page=-1')->assertSessionHasErrors('ventas_page');
    }

    public function test_previsualizacion_bolivia_dia_abierto_terminado_vacio_y_fecha_invalida(): void
    {
        // UTC ya es 7 de octubre; Bolivia todavía es 6. La venta a las 02:00 UTC pertenece al 6.
        $this->get('/admin/caja/previsualizacion')->assertOk()->assertViewHas('fecha', '2026-10-06')
            ->assertSee('Día operativo abierto')->assertSee('Bs 10,10')->assertSee('Consulta provisional');
        $this->get('/admin/caja/previsualizacion?fecha=2026-10-05')->assertOk()
            ->assertSee('Día operativo terminado')->assertSee('Ya existe un cierre oficial')->assertSee('Sin ventas completadas');
        $this->get('/admin/caja/previsualizacion?fecha=2026-10-03')->assertOk()->assertSee('Bs 0,00')->assertSee('Sin ventas completadas');
        $this->get('/admin/caja/previsualizacion?fecha=2026-10-07')->assertSessionHasErrors('fecha');
        $this->get('/admin/caja/previsualizacion?fecha=2026-02-30')->assertSessionHasErrors('fecha');
    }

    public function test_detalle_usa_instantaneas_y_escapa_observaciones_y_motivos(): void
    {
        DB::table('ventas')->update(['total' => '999.99', 'metodo_pago' => 'CAMBIO_ACTUAL']);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->get('/admin/caja/cierres/'.$this->cierre)->assertOk()->assertSee('Método histórico')
            ->assertSee('Bs 10,10')->assertSee('Bs -1,01')->assertSee('05/10/2026 22:00:00')
            ->assertSee('Historial de versiones')->assertSee('<script>observacion()</script>')
            ->assertDontSee('<script>observacion()</script>', false)->assertDontSee('<img src=x onerror=motivo()>', false)
            ->assertDontSee('CAMBIO_ACTUAL')->assertDontSee('Bs 999,99');
        $sql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        $this->assertStringNotContainsString('from "ventas"', $sql);
        $this->assertStringNotContainsString('from "pedidos"', $sql);
    }

    public function test_version_se_resuelve_solo_dentro_del_cierre_y_no_cambia_vigencia(): void
    {
        $this->get('/admin/caja/cierres/'.$this->cierre.'/versiones/'.$this->v1)->assertOk()
            ->assertSee('Histórica conservada')->assertViewHas('version', fn ($v) => $v->id === $this->v1);
        $this->get('/admin/caja/cierres/'.$this->cierre.'/versiones/'.$this->v2)->assertOk()->assertSee('Vigente');
        $this->get('/admin/caja/cierres/'.$this->cierre.'/versiones/'.$this->otro)->assertNotFound();
        $this->get('/admin/caja/cierres/999999')->assertNotFound();
        $this->get('/admin/caja/cierres/no-es-id')->assertNotFound();
    }

    public function test_historico_sin_fecha_no_se_oficializa_y_se_escapa(): void
    {
        $this->get('/admin/caja/cierres/'.$this->historico)->assertOk()->assertSee('Registro histórico no oficial')
            ->assertSee('No hay versiones financieras registradas')->assertSee('<script>legado()</script>')
            ->assertDontSee('<script>legado()</script>', false);
        $this->assertNull(DB::table('cierre_cajas')->where('id', $this->historico)->value('fecha_operativa'));
    }

    public function test_inconsistencia_financiera_no_muestra_cero_ni_importes_parciales(): void
    {
        DB::table('ventas')->update(['monto_efectivo' => '9.00']);
        $this->get('/admin/caja/previsualizacion?fecha=2026-10-06')->assertStatus(422)
            ->assertSee('existen inconsistencias')->assertViewHas('resumen', null)
            ->assertDontSee('Bs 0,00')->assertDontSee('SQLSTATE');
    }

    public function test_error_sql_se_muestra_sin_traza_aun_con_debug(): void
    {
        config(['app.debug' => true]);
        DB::connection()->beforeExecuting(static function ($sql) {
            if (str_contains($sql, 'cierre_cajas')) throw new \RuntimeException('SQLSTATE password=SECRETO_INTERNO');
        });
        $this->get('/admin/caja')->assertStatus(503)->assertSee('Consulta de caja no disponible')
            ->assertDontSee('SQLSTATE')->assertDontSee('SECRETO_INTERNO')->assertDontSee('Stack trace');
    }

    public function test_las_cuatro_rutas_de_consulta_siguen_sin_escrituras_ni_acceso_al_crud_antiguo(): void
    {
        $tablas = ['cierre_cajas', 'cierre_caja_versiones', 'cierre_caja_version_ventas', 'bitacoras',
            'ventas', 'pedidos', 'stocks', 'movimiento_inventarios'];
        $antes = [];
        foreach ($tablas as $tabla) $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        DB::enableQueryLog(); DB::flushQueryLog();
        foreach (array_merge($this->urls(), ['/admin/caja/cierres/'.$this->historico]) as $url) $this->get($url)->assertOk();
        foreach (DB::getQueryLog() as $consulta) {
            $this->assertDoesNotMatchRegularExpression('/\A\s*(insert|update|delete|replace|alter|drop|create)\b/i', $consulta['query']);
            $this->assertStringNotContainsString('GET_LOCK', $consulta['query']);
        }
        foreach ($antes as $tabla => $contenido) $this->assertSame($contenido, DB::table($tabla)->orderBy('id')->get()->toJson());
        $rutas = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->getActionName(), \App\Http\Controllers\CajaController::class.'@'));
        $this->assertCount(4, $rutas);
        foreach ($rutas as $ruta) {
            $this->assertSame(['GET','HEAD'], $ruta->methods());
            $this->assertContains('auth', $ruta->gatherMiddleware());
            $this->assertContains('permiso:consultar_caja', $ruta->gatherMiddleware());
        }
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($r) => str_contains($r->getActionName(), 'CierreCajasController')));
        foreach (['post','put','patch','delete'] as $metodo) foreach ($this->urls() as $url) $this->{$metodo}($url)->assertStatus(405);
        foreach ($antes as $tabla => $contenido) $this->assertSame($contenido, DB::table($tabla)->orderBy('id')->get()->toJson());
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
    }

    public function test_historial_y_detalles_tienen_paginacion_independiente(): void
    {
        for ($numero = 3; $numero <= 23; $numero++) $this->versionFixture($this->cierre, $numero, '10.10');
        $this->get('/admin/caja/cierres/'.$this->cierre.'?versiones_page=2')->assertOk()
            ->assertViewHas('versiones', fn ($p) => $p->total() === 23 && $p->currentPage() === 2 && $p->count() === 3)
            ->assertViewHas('version', fn ($v) => $v->numero_version === 23);
        $this->get('/admin/caja/cierres/'.$this->cierre.'/versiones/'.$this->v1.'?ventas_page=2')->assertOk()
            ->assertViewHas('detalles', fn ($p) => $p->total() === 1 && $p->currentPage() === 2 && $p->count() === 0);
        $this->get('/admin/caja/cierres/'.$this->cierre.'?versiones_page=abc')->assertSessionHasErrors('versiones_page');
    }

    public function test_formato_decimal_exacto_sin_float_incluso_para_importes_grandes(): void
    {
        $this->assertSame('Bs 99.999.999.999.999,99', ImporteCajaVista::formatear('99999999999999.99'));
        $this->assertSame('Bs -0,01', ImporteCajaVista::formatear('-0.01'));
        $this->assertSame('No disponible', ImporteCajaVista::formatear(0.1));
        // SQLite tiene afinidad numérica, no DECIMAL exacto de MariaDB: el máximo se verifica arriba como cadena.
        DB::table('cierre_caja_versiones')->where('id', $this->v2)->update(['total_ventas' => '1234567890.25']);
        $this->get('/admin/caja/cierres/'.$this->cierre)->assertOk()->assertSee('Bs 1.234.567.890,25');
    }

    public function test_menu_caja_visible_con_permiso_y_oculto_sin_permiso(): void
    {
        $this->get('/admin/caja')->assertOk()->assertSee('id="paquete-pedidos"', false)
            ->assertSee('aria-controls="paquete-pedidos"', false)->assertSee('Pedidos, Ventas y Caja')
            ->assertSee('Consultar cierres')->assertSee('Previsualización del día')
            ->assertDontSee('Registrar pedido')->assertDontSee('Registrar venta y pago');
        DB::table('rol_permisos')->delete();
        $this->get('/admin/mi-contrasena')->assertOk()->assertDontSee('id="paquete-pedidos"', false);
        foreach ($this->urls() as $url) $this->get($url)->assertForbidden();
    }

    public function test_navegacion_y_estados_visuales_son_accesibles_y_sin_borradores(): void
    {
        foreach ($this->urls() as $url) {
            $respuesta = $this->get($url)->assertOk()->assertSee('Navegación de caja')
                ->assertSee('Las operaciones financieras de caja todavía no están habilitadas')
                ->assertSee('css/caja.css')->assertDontSee('data-borrador', false)
                ->assertDontSee('sessionStorage', false)->assertDontSee('localStorage', false);
            $dom = new \DOMDocument();
            @$dom->loadHTML($respuesta->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame(0, $xpath->query('//*[@id="paquete-pedidos" and @hidden]')->length);
            $this->assertSame(1, $xpath->query('//button[@aria-controls="paquete-pedidos" and @aria-expanded="true"]')->length);
            $this->assertSame(1, $xpath->query('//nav[@aria-label="Navegación de caja"]//a[@href="'.route('admin.caja.index').'"]')->length);
            $this->assertSame(1, $xpath->query('//nav[@aria-label="Navegación de caja"]//a[@href="'.route('admin.caja.previsualizacion').'"]')->length);
        }
        $this->get('/admin/caja/cierres/'.$this->cierre)->assertOk()->assertSee('Cierre oficial')
            ->assertSee('id="historial-versiones"', false)->assertSee('#historial-versiones', false)
            ->assertSee('Vigente')->assertSee('Histórica conservada')->assertSee('tabindex="0"', false);
        $this->get('/admin/caja/cierres/'.$this->cierre.'/versiones/'.$this->v1)->assertOk()
            ->assertSee('Ver cierre vigente e historial')->assertSee('Histórica conservada');
    }

    public function test_p4_filtra_individualmente_pedidos_ventas_y_caja_sin_crear_p7(): void
    {
        foreach ([['registrar_pedidos'], ['cobrar_ventas'], ['consultar_caja'],
            ['registrar_pedidos', 'cobrar_ventas', 'consultar_caja']] as $permisos) {
            DB::table('rol_permisos')->delete();
            foreach ($permisos as $nombre) {
                $id = DB::table('permisos')->where('nombre', $nombre)->value('id')
                    ?? DB::table('permisos')->insertGetId(['nombre' => $nombre]);
                DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $id]);
            }
            $respuesta = $this->get('/admin/mi-contrasena')->assertOk()->assertSee('Pedidos, Ventas y Caja');
            $dom = new \DOMDocument();
            @$dom->loadHTML($respuesta->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//*[@id="paquete-pedidos"]')->length);
            $this->assertSame(0, $xpath->query('//*[@id="paquete-caja"]')->length);
            foreach (['registrar_pedidos' => 'admin.pedido.crear', 'cobrar_ventas' => 'admin.indexventa',
                'consultar_caja' => 'admin.caja.index'] as $permiso => $ruta) {
                $this->assertSame(in_array($permiso, $permisos, true) ? 1 : 0,
                    $xpath->query('//*[@id="paquete-pedidos"]//a[@href="'.route($ruta).'"]')->length);
            }
            if (in_array('consultar_caja', $permisos, true)) $this->get('/admin/caja')->assertOk();
            else $this->get('/admin/caja')->assertForbidden();
        }
    }

    public function test_se_conservan_los_seis_paquetes_y_solo_p4_se_abre_en_caja(): void
    {
        foreach (['gestion_usuarios', 'gestion_productos', 'gestion_proveedores', 'ver_reportes', 'gestion_promociones'] as $nombre) {
            $id = DB::table('permisos')->insertGetId(['nombre' => $nombre]);
            DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $id]);
        }
        $respuesta = $this->get('/admin/caja')->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($respuesta->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(6, $xpath->query('//section[@class="menu-paquete"]')->length);
        foreach (['usuarios', 'productos', 'inventario', 'pedidos', 'reportes', 'promociones'] as $grupo) {
            $this->assertSame(1, $xpath->query('//*[@id="paquete-'.$grupo.'"]')->length);
            $this->assertSame($grupo === 'pedidos' ? 0 : 1, $xpath->query('//*[@id="paquete-'.$grupo.'" and @hidden]')->length);
        }
        foreach (['admin.indexusuario', 'admin.indexproducto', 'admin.indexproveedor', 'admin.reportes.diarias', 'admin.indexpromocion'] as $ruta) {
            $this->assertSame(1, $xpath->query('//nav[@id="menu-principal"]//a[@href="'.route($ruta).'"]')->length);
        }
        $fuente = file_get_contents(resource_path('views/layouts/menu-paquetes.blade.php'));
        foreach (['P1', 'P2', 'P3', 'P4', 'P5', 'P6'] as $codigo) $this->assertStringContainsString("'codigo' => '".$codigo."'", $fuente);
        $this->assertStringNotContainsString("'codigo' => 'P7'", $fuente);
    }

    public function test_paginacion_visual_conserva_filtros_y_separa_historial(): void
    {
        for ($i = 1; $i <= 12; $i++) $this->cabecera('2026-09-'.str_pad((string)$i, 2, '0', STR_PAD_LEFT));
        $this->get('/admin/caja?tipo=todos&por_pagina=10')->assertOk()->assertSee('Página 1 de 2')
            ->assertSee('Páginas de cierres')->assertSee('por_pagina=10')->assertSee('page=2');
    }

    public function test_renderizados_responsive_conservan_importes_y_pueden_exportarse_para_verificacion_aislada(): void
    {
        // Exportación opcional de fixtures sintéticos; el navegador nunca se conecta con Laravel ni con una BD.
        $directorio = getenv('KARMA_CAJA_HTML_DIR');
        if ($directorio !== false && $directorio !== '') {
            $real = realpath($directorio);
            $temporal = realpath(sys_get_temp_dir());
            if (!$real || !$temporal || !str_starts_with(strtolower($real.DIRECTORY_SEPARATOR), strtolower($temporal.DIRECTORY_SEPARATOR))) {
                throw new LogicException('La exportación solo admite una carpeta temporal existente.');
            }
        }
        foreach (['listado' => '/admin/caja?tipo=todos', 'previsualizacion' => '/admin/caja/previsualizacion',
            'detalle' => '/admin/caja/cierres/'.$this->cierre,
            'version' => '/admin/caja/cierres/'.$this->cierre.'/versiones/'.$this->v1,
            'historico' => '/admin/caja/cierres/'.$this->historico,
            'vacio' => '/admin/caja?desde=2020-01-01&hasta=2020-01-02'] as $nombre => $url) {
            $respuesta = $this->get($url)->assertOk();
            $dom = new \DOMDocument();
            @$dom->loadHTML($respuesta->getContent());
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//div[contains(@class,"caja-tabla")]') as $tabla) {
                $this->assertSame('0', $tabla->getAttribute('tabindex'));
                $this->assertSame('region', $tabla->getAttribute('role'));
                $this->assertNotSame('', $tabla->getAttribute('aria-label'));
            }
            if ($directorio) file_put_contents($real.DIRECTORY_SEPARATOR.$nombre.'.html', $respuesta->getContent());
        }
        $this->assertSame('Bs -0,01', ImporteCajaVista::formatear('-0.01'));
    }
}
