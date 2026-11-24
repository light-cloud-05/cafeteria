<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{Bitacora, Pedido, Producto, Stock, TransaccionPago, User, Venta};
use App\Services\Caja\{AutorizacionCaja, CierreDiarioService, CoordinacionCobrosCaja, CoordinadorCobrosCaja, ReconciliacionCajaService, RectificacionCierreDiarioService, ConsolidacionCajaService};
use App\Services\Pagos\{AislamientoPagosSimulados, EstadoPago, FinalizarCobroService, IntentosPagoService};
use App\Services\ReservaPedidoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PagosSimuladosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Fallar antes de conectar si el destino no es efímero.
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new LogicException('CU26 solo se prueba en SQLite :memory: sin URL alternativa.');
        }
        foreach (DB::select('PRAGMA database_list') as $base) $this->assertSame('', $base->file);
        $this->assertSame(1, (int)DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        foreach (glob(database_path('migrations/*.php')) as $archivo) (require $archivo)->up();
        DB::table('roles')->insert(['rol_id' => 1, 'nombre' => 'Cajero pruebas', 'estado' => 1]);
        DB::table('usuarios')->insert(['id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Responsable ficticio',
            'nombreUsuario' => 'cu26-prueba', 'password' => 'no-es-credencial', 'estado' => 1]);
        foreach (['cobrar_ventas','modificar_pedidos','consultar_caja','confirmar_cierre_caja','rectificar_cierre_caja','consultar_bitacora'] as $nombre) {
            $id = DB::table('permisos')->insertGetId(['nombre' => $nombre]);
            DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $id]);
        }
        $this->actingAs(User::findOrFail(1));
        DB::table('categorias')->insert(['id' => 1, 'nombre' => 'Prueba', 'estado' => 1]);
        Producto::create(['nombre' => 'Café prueba', 'precio' => '12.35', 'disponible' => true, 'categoria_id' => 1]);
        Stock::create(['producto_id' => 1, 'stock_actual' => 20, 'stock_minimo' => 0]);
        config(['pagos.simulacion.activa' => true, 'pagos.simulacion.resultado' => 'PENDIENTE']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function pedido(): Pedido
    {
        return DB::transaction(function () {
            $p = Pedido::create(['usuario_id' => 1, 'fechaHora' => now(), 'estado' => 'Registrado',
                'subtotal' => '24.70', 'descuento' => '0.00', 'total' => '24.70']);
            $p->detalles()->create(['producto_id' => 1, 'cantidad' => 2, 'precioUnitario' => '12.35', 'subtotal' => '24.70', 'opcion' => '']);
            app(ReservaPedidoService::class)->mover($p);
            return $p->fresh();
        });
    }

    private function intento(?Pedido $pedido = null, string $medio = 'QR_SIMULADO', ?string $clave = null): TransaccionPago
    {
        $pedido ??= $this->pedido();
        return app(IntentosPagoService::class)->crear($pedido->id, $medio, $pedido->versionCobro(), $clave ?? (string)Str::uuid());
    }

    private function rechaza(callable $accion, string $clase = ValidationException::class): void
    {
        try { $accion(); $this->fail('La operación debió rechazarse.'); }
        catch (\Throwable $e) { $this->assertInstanceOf($clase, $e); }
    }

    public function test_referencia_idempotencia_e_importe_del_servidor(): void
    {
        $p = $this->pedido(); $clave = (string)Str::uuid();
        $a = $this->intento($p, 'QR_SIMULADO', $clave);
        $b = $this->intento($p, 'QR_SIMULADO', strtoupper($clave));
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, TransaccionPago::count());
        $this->assertTrue(Str::isUuid($a->referencia));
        $this->assertSame('24.70', $a->monto);
        $this->assertSame('BOB', $a->moneda);
        $this->assertSame('SIMULACION_LOCAL', $a->origen);
        $this->assertSame($p->id, $a->pedido->id);
        $this->assertSame(1, $a->usuario->id);
        $this->assertNull($a->venta);
        $this->rechaza(fn () => $this->intento($p, 'TARJETA_SIMULADA', $clave));
        $this->rechaza(fn () => $this->intento($this->pedido(), 'QR_SIMULADO', $clave));
    }

    public function test_un_solo_pendiente_y_reintento_tras_rechazo(): void
    {
        $p = $this->pedido(); $a = $this->intento($p);
        $this->rechaza(fn () => $this->intento($p));
        config(['pagos.simulacion.resultado' => 'RECHAZADO']);
        $r = app(IntentosPagoService::class)->procesar($a->id);
        $this->assertSame(EstadoPago::RECHAZADO, $r->estado);
        $this->assertSame(0, Venta::count());
        $this->assertSame('Registrado', $p->fresh()->estado);
        $b = $this->intento($p);
        $this->assertNotSame($a->referencia, $b->referencia);
        $this->assertSame(2, $p->transaccionesPago()->count());
        $this->assertSame(18, (int)Stock::first()->stock_actual);
    }

    public function test_aprobacion_qr_atomica_y_repetida_no_reprocesa(): void
    {
        $p = $this->pedido(); $a = $this->intento($p);
        config(['pagos.simulacion.resultado' => 'APROBADO']);
        $s = app(IntentosPagoService::class);
        $aprobado = $s->procesar($a->id);
        $this->assertSame(EstadoPago::APROBADO, $aprobado->estado);
        $this->assertSame($aprobado->venta_id, $s->procesar($a->id)->venta_id);
        $this->assertSame(1, Venta::count());
        $this->assertSame('Pagado', $p->fresh()->estado);
        $this->assertSame(18, (int)Stock::first()->stock_actual);
        $this->assertSame('QR_SIMULADO', $aprobado->venta->metodo_pago);
        $this->assertEquals('24.70', $aprobado->venta->monto_qr);
        $this->assertEquals(0, $aprobado->venta->monto_tarjeta);
        $this->assertSame('SIMULACION_LOCAL', $aprobado->venta->origen);
        $this->assertNotNull($aprobado->confirmado_en);
        $this->assertSame($aprobado->id, $this->intento($p, 'QR_SIMULADO', $a->clave_idempotencia)->id);
        $this->rechaza(fn () => $this->intento($p));
        $this->rechaza(fn () => app(FinalizarCobroService::class)->presencial($p->id, [
            'version_pedido' => $p->versionCobro(), 'metodo_pago' => 'QR']));
        $this->assertSame(2, Bitacora::where('tablaAfectada', 'transacciones_pago')->count());
    }

    public function test_tarjeta_no_almacena_datos_de_tarjeta_y_respeta_desglose(): void
    {
        $a = $this->intento(null, 'TARJETA_SIMULADA');
        config(['pagos.simulacion.resultado' => 'APROBADO']);
        $a = app(IntentosPagoService::class)->procesar($a->id);
        $this->assertEquals('24.70', $a->venta->monto_tarjeta);
        $this->assertEquals(0, $a->venta->monto_qr);
        $this->assertEquals(0, $a->venta->monto_efectivo);
        $this->assertEquals(0, $a->venta->cambio);
        foreach (['pan','cvv','numero_tarjeta','payload','fecha_vencimiento'] as $campo) {
            $this->assertNotContains($campo, Schema::getColumnListing('transacciones_pago'));
        }
    }

    public function test_pendiente_no_crea_venta_y_bloquea_edicion_cancelacion_y_cobro_manual(): void
    {
        $p = $this->pedido(); $a = $this->intento($p);
        $this->assertSame(EstadoPago::PENDIENTE, app(IntentosPagoService::class)->procesar($a->id)->estado);
        $this->put('/admin/pedidos/editar/'.$p->id, ['detalles' => [['producto_id' => 1, 'cantidad' => 1]]])->assertSessionHasErrors('pedido');
        $this->post('/admin/pedidos/cancelar/'.$p->id)->assertSessionHasErrors('pedido');
        $this->post('/admin/ventas/cobrar/'.$p->id, ['version_pedido' => $p->versionCobro(),
            'metodo_pago' => 'QR', 'estado' => 'APROBADO', 'total' => '0.01'])->assertSessionHasErrors('pedido');
        $this->assertSame(0, Venta::count());
        $this->assertEquals('24.70', $p->fresh()->total);
        $this->assertSame(18, (int)Stock::first()->stock_actual);
    }

    public function test_cancelar_libera_intento_y_permite_cancelar_pedido_con_devolucion(): void
    {
        $p = $this->pedido(); $a = $this->intento($p);
        $this->assertSame(EstadoPago::CANCELADO, app(IntentosPagoService::class)->cancelar($a->id)->estado);
        $this->post('/admin/pedidos/cancelar/'.$p->id)->assertSessionHasNoErrors();
        $this->assertSame('Cancelado', $p->fresh()->estado);
        $this->assertSame(20, (int)Stock::first()->stock_actual);
        $this->assertSame(0, Venta::count());
    }

    public function test_expiracion_y_reintento_no_reutilizan_intento(): void
    {
        $p = $this->pedido(); $a = $this->intento($p);
        $this->travel(301)->seconds();
        $b = $this->intento($p);
        $this->assertSame(EstadoPago::EXPIRADO, $a->fresh()->estado);
        $this->assertNotSame($a->id, $b->id);
        $this->travel(301)->seconds();
        config(['pagos.simulacion.resultado' => 'APROBADO']);
        $this->assertSame(EstadoPago::EXPIRADO, app(IntentosPagoService::class)->procesar($b->id)->estado);
        $this->assertSame(0, Venta::count());
    }

    public function test_version_e_importe_alterados_impiden_aprobar(): void
    {
        $p = $this->pedido();
        $this->rechaza(fn () => app(IntentosPagoService::class)->crear($p->id, 'QR_SIMULADO', str_repeat('0',64), (string)Str::uuid()));
        $a = $this->intento($p);
        DB::table('pedidos')->where('id', $p->id)->update(['total' => '0.01']);
        config(['pagos.simulacion.resultado' => 'APROBADO']);
        $this->rechaza(fn () => app(IntentosPagoService::class)->procesar($a->id));
        $this->assertSame(0, Venta::count());
        $this->assertSame(EstadoPago::PENDIENTE, $a->fresh()->estado);
    }

    public function test_sin_sesion_permiso_o_responsable_correcto_no_se_resuelve(): void
    {
        $a = $this->intento();
        auth()->forgetUser();
        $this->rechaza(fn () => app(IntentosPagoService::class)->procesar($a->id), AuthenticationException::class);
        $this->actingAs(User::findOrFail(1));
        DB::table('rol_permisos')->where('idPermiso', DB::table('permisos')->where('nombre','cobrar_ventas')->value('id'))->delete();
        $this->rechaza(fn () => app(IntentosPagoService::class)->procesar($a->id), AuthorizationException::class);
        $this->assertSame(EstadoPago::PENDIENTE, $a->fresh()->estado);
        $this->assertSame(0, Venta::count());
    }

    public function test_simulacion_desactivada_o_destino_persistente_se_rechazan(): void
    {
        config(['pagos.simulacion.activa' => false]);
        $this->rechaza(fn () => app(AislamientoPagosSimulados::class)->verificar(), LogicException::class);
        config(['pagos.simulacion.activa' => true, 'database.connections.sqlite.database' => 'archivo.sqlite']);
        $this->rechaza(fn () => app(AislamientoPagosSimulados::class)->verificar(), \RuntimeException::class);
    }

    public function test_otro_cajero_no_puede_resolver_ni_reutilizar_clave_ajena(): void
    {
        $p = $this->pedido(); $a = $this->intento($p);
        DB::table('usuarios')->insert(['id' => 2, 'rol_id' => 1, 'nombreCompleto' => 'Otro cajero ficticio',
            'nombreUsuario' => 'otro-cu26', 'password' => 'no-es-credencial', 'estado' => 1]);
        $this->actingAs(User::findOrFail(2));
        $this->rechaza(fn () => app(IntentosPagoService::class)->procesar($a->id), AuthorizationException::class);
        $this->rechaza(fn () => $this->intento($p, 'QR_SIMULADO', $a->clave_idempotencia));
        $this->assertSame(0, Venta::count());
    }

    public function test_permiso_revocado_durante_espera_del_coordinador_se_revalida(): void
    {
        $a = $this->intento();
        app()->instance(CoordinacionCobrosCaja::class, new class implements CoordinacionCobrosCaja {
            public function exclusivo(\Closure $operacion): mixed
            {
                DB::table('rol_permisos')->where('idPermiso', DB::table('permisos')->where('nombre','cobrar_ventas')->value('id'))->delete();
                return $operacion();
            }
            public function permiteCierreDefinitivo(): bool { return false; }
        });
        $this->rechaza(fn () => app(IntentosPagoService::class)->procesar($a->id), AuthorizationException::class);
        $this->assertSame(EstadoPago::PENDIENTE, $a->fresh()->estado);
        $this->assertSame(0, Venta::count());
    }

    public function test_monto_de_intento_manipulado_y_dia_cerrado_no_crean_venta(): void
    {
        $a = $this->intento();
        config(['pagos.simulacion.resultado' => 'APROBADO']);
        DB::table('transacciones_pago')->where('id',$a->id)->update(['monto' => '0.01']);
        $this->rechaza(fn () => app(IntentosPagoService::class)->procesar($a->id));
        DB::table('transacciones_pago')->where('id',$a->id)->update(['monto' => '24.70']);
        DB::table('cierre_cajas')->insert(['idUsuario' => 1, 'fechaInicio' => now()->utc()->format('Y-m-d H:i:s'),
            'fecha_operativa' => now()->toDateString()]);
        $this->rechaza(fn () => app(IntentosPagoService::class)->procesar($a->id));
        $this->assertSame(0, Venta::count());
        $this->assertSame(EstadoPago::PENDIENTE, $a->fresh()->estado);
    }

    public function test_transiciones_invalidas_e_historial_no_editable(): void
    {
        foreach (EstadoPago::cases() as $origen) foreach (EstadoPago::cases() as $destino) {
            if ($origen === EstadoPago::PENDIENTE && $destino !== EstadoPago::PENDIENTE) $origen->verificarTransicion($destino);
            else $this->rechaza(fn () => $origen->verificarTransicion($destino), LogicException::class);
        }
        $a = $this->intento();
        $a->monto = '0.01';
        $this->rechaza(fn () => $a->saveQuietly(), LogicException::class);
        $this->rechaza(fn () => $a->deleteQuietly(), LogicException::class);
        app(IntentosPagoService::class)->cancelar($a->id);
        config(['pagos.simulacion.resultado' => 'APROBADO']);
        $this->assertSame(EstadoPago::CANCELADO, app(IntentosPagoService::class)->procesar($a->id)->estado);
    }

    public function test_indices_unicos_y_foreign_keys_rechazan_escrituras_incompatibles(): void
    {
        $a = $this->intento();
        $datos = $a->getRawOriginal(); unset($datos['id'], $datos['pedido_activo_id']);
        $datos['referencia'] = (string)Str::uuid(); $datos['clave_idempotencia'] = (string)Str::uuid();
        $this->rechaza(fn () => DB::table('transacciones_pago')->insert($datos), QueryException::class);
        $datos['estado'] = 'RECHAZADO'; $datos['referencia'] = $a->referencia;
        $this->rechaza(fn () => DB::table('transacciones_pago')->insert($datos), QueryException::class);
        $datos['referencia'] = (string)Str::uuid(); $datos['clave_idempotencia'] = $a->clave_idempotencia;
        $this->rechaza(fn () => DB::table('transacciones_pago')->insert($datos), QueryException::class);
        $datos['clave_idempotencia'] = (string)Str::uuid(); $datos['pedido_id'] = 999;
        $this->rechaza(fn () => DB::table('transacciones_pago')->insert($datos), QueryException::class);
    }

    public function test_fallo_al_finalizar_revierte_venta_estado_y_auditoria(): void
    {
        $a = $this->intento();
        config(['pagos.simulacion.resultado' => 'APROBADO']);
        DB::unprepared("CREATE TRIGGER impedir_resolucion BEFORE UPDATE ON transacciones_pago BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        $this->rechaza(fn () => app(IntentosPagoService::class)->procesar($a->id), QueryException::class);
        $this->assertSame(0, Venta::count());
        $this->assertSame('Registrado', $a->pedido->estado);
        $this->assertSame(EstadoPago::PENDIENTE, $a->fresh()->estado);
        $this->assertSame(18, (int)Stock::first()->stock_actual);
        $this->assertSame(1, Bitacora::where('tablaAfectada','transacciones_pago')->count());
    }

    public function test_cobros_presenciales_conservan_importes_y_omiten_totales_del_cliente(): void
    {
        foreach (['Efectivo','QR','Mixto'] as $metodo) {
            $p = $this->pedido();
            $this->post('/admin/ventas/cobrar/'.$p->id, ['version_pedido' => $p->versionCobro(),
                'metodo_pago' => $metodo, 'monto_recibido' => '30.00', 'monto_qr' => '10.00',
                'total' => '0.01', 'estado' => 'APROBADO', 'origen' => 'SIMULACION_LOCAL'])->assertSessionHasNoErrors();
            $v = $p->venta()->firstOrFail();
            $this->assertEquals('24.70', $v->total);
            $this->assertEquals(0, $v->monto_tarjeta);
            $this->assertSame('OPERACION', $v->origen);
        }
        $this->assertSame(0, TransaccionPago::count());
        $this->assertSame(14, (int)Stock::first()->stock_actual);
    }

    public function test_caja_tarjeta_concilia_rectifica_y_consolida_sin_habilitar_produccion(): void
    {
        $this->travelTo(now()->setDate(2026,11,20)->setTime(12,0));
        $a = $this->intento(null, 'TARJETA_SIMULADA');
        config(['pagos.simulacion.resultado' => 'APROBADO']);
        app(IntentosPagoService::class)->procesar($a->id);
        $this->travel(1)->days();
        $doble = new class implements CoordinacionCobrosCaja {
            public function exclusivo(\Closure $operacion): mixed { return (new CoordinadorCobrosCaja)->exclusivo($operacion); }
            public function permiteCierreDefinitivo(): bool { return true; }
        };
        $cierre = (new CierreDiarioService($doble))->confirmar('2026-11-20', '0.00');
        $this->assertSame('24.70', $cierre->tarjeta_registrado);
        $this->assertSame('0.00', $cierre->efectivo_esperado);
        $this->assertSame('SIMULACION_LOCAL', $cierre->detalles->first()->origen);
        $nueva = (new RectificacionCierreDiarioService($doble))->rectificar($cierre->cierre_caja_id, 1, '0.00', 'Prueba local', 'Observación ficticia');
        $this->assertSame('24.70', $nueva->tarjeta_registrado);
        $this->assertSame('24.70', $nueva->detalles->first()->monto_tarjeta);
        $this->assertSame('CONFIRMACION_OBSERVADA', app(ReconciliacionCajaService::class)->consultar('2026-11-20')['estado']);
        $this->assertSame('24.70', app(ConsolidacionCajaService::class)->mensual(2026,11)['totales']['tarjeta_registrado']);
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
    }
}
