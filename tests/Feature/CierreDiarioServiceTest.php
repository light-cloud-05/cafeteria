<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{Bitacora, CierreCaja, CierreCajaVersion, CierreCajaVersionVenta, User};
use App\Services\Caja\{CierreDiarioService, CoordinacionCobrosCaja};
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class CierreDiarioServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new LogicException('Estas pruebas solo admiten SQLite :memory: sin URL alternativa.');
        }
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach (DB::select('PRAGMA database_list') as $base) {
            $this->assertSame('', $base->file);
        }
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            (require $archivo)->up();
        }
        DB::table('roles')->insert(['rol_id' => 1, 'nombre' => 'Prueba caja', 'estado' => 1]);
        DB::table('usuarios')->insert([
            'id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Actor de prueba',
            'nombreUsuario' => 'actor-caja', 'password' => 'no-es-una-credencial', 'estado' => 1,
        ]);
        foreach (['consultar_caja', 'confirmar_cierre_caja', 'rectificar_cierre_caja'] as $nombre) {
            $permiso = DB::table('permisos')->insertGetId(['nombre' => $nombre]);
            DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $permiso]);
        }
        $this->actingAs(User::findOrFail(1));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 00:05:00', 'America/La_Paz'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    /** Simulación secuencial SOLO en pruebas; no demuestra exclusión entre procesos. */
    private function servicio(?Closure $antes = null, ?Closure $despues = null): CierreDiarioService
    {
        return new CierreDiarioService(new class($antes, $despues) implements CoordinacionCobrosCaja
        {
            public function __construct(private ?Closure $antes, private ?Closure $despues)
            {
            }

            public function exclusivo(Closure $operacion): mixed
            {
                if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
                    throw new LogicException('El doble de coordinación solo permite SQLite en memoria.');
                }
                ($this->antes ?? static fn () => null)();
                try {
                    return $operacion();
                } finally {
                    ($this->despues ?? static fn () => null)();
                }
            }

            public function permiteCierreDefinitivo(): bool
            {
                return true; // Exclusivamente el doble secuencial de estas pruebas en memoria.
            }
        });
    }

    private function venta(array $datos = [], array $datosPedido = []): int
    {
        $total = $datos['total'] ?? '10.00';
        $pedido = DB::table('pedidos')->insertGetId(array_replace([
            'usuario_id' => 1, 'fechaHora' => '2026-10-06 03:00:00',
            'total' => $total, 'estado' => 'Pagado',
        ], $datosPedido));

        return DB::table('ventas')->insertGetId(array_replace([
            'pedido_id' => $pedido, 'usuario_id' => 1, 'fecha_hora' => '2026-10-06 18:00:00',
            'total' => $total, 'monto_efectivo' => $total, 'monto_qr' => '0.00',
            'metodo_pago' => 'Efectivo', 'monto_recibido' => $total, 'cambio' => '0.00',
            'estado' => 'Completado',
        ], $datos));
    }

    private function sinCierre(): void
    {
        $this->assertSame(0, CierreCaja::count());
        $this->assertSame(0, CierreCajaVersion::count());
        $this->assertSame(0, CierreCajaVersionVenta::count());
        $this->assertSame(0, Bitacora::where('accion', 'CIERRE_CAJA')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_efectivo_qr_y_mixto_no_suman_cambio_ni_descontar_promocion_de_nuevo(): void
    {
        $this->venta(['monto_recibido' => '20.00', 'cambio' => '10.00'], ['subtotal' => '15.00', 'descuento' => '5.00']);
        $this->venta(['total' => '20.00', 'metodo_pago' => 'QR', 'monto_efectivo' => '0.00', 'monto_qr' => '20.00'], ['estado' => 'Completado']);
        $this->venta(['total' => '24.70', 'metodo_pago' => 'Mixto', 'monto_efectivo' => '14.70',
            'monto_qr' => '10.00', 'monto_recibido' => '30.00', 'cambio' => '5.30']);
        $servicio = $this->servicio();
        $resumen = $servicio->previsualizar('2026-10-06');
        $this->assertSame(['fecha_operativa' => '2026-10-06', 'cantidad_ventas' => 3,
            'total_ventas' => '54.70', 'efectivo_esperado' => '24.70', 'qr_registrado' => '30.00', 'tarjeta_registrado' => '0.00'], $resumen);
        $antes = [];
        foreach (['pedidos', 'ventas', 'productos', 'stocks', 'movimiento_inventarios'] as $tabla) {
            $antes[$tabla] = DB::table($tabla)->get()->toJson();
        }
        $version = $servicio->confirmar('2026-10-06', '24.70');
        $this->assertSame('54.70', $version->total_ventas);
        $this->assertSame('0.00', $version->diferencia);
        $this->assertSame(3, $version->detalles->count());
        $this->assertSame(1, $version->responsable_id);
        $this->assertSame(1, Bitacora::where('accion', 'CIERRE_CAJA')->count());
        foreach ($antes as $tabla => $contenido) {
            $this->assertSame($contenido, DB::table($tabla)->get()->toJson());
        }
    }

    public function test_importes_exactos_y_faltante_de_un_centavo(): void
    {
        $this->venta(['total' => '0.10']);
        $this->venta(['total' => '0.20']);
        $version = $this->servicio()->confirmar('2026-10-06', '0.29', 'Recuento con faltante de un centavo');
        $this->assertSame('0.30', $version->total_ventas);
        $this->assertSame('-0.01', $version->diferencia);
    }

    public function test_fecha_de_cobro_bolivia_incluye_extremos_correctos_sin_usar_fecha_pedido(): void
    {
        $this->venta(['fecha_hora' => '2026-10-06 03:59:59']);
        $inicio = $this->venta(['fecha_hora' => '2026-10-06 04:00:00']);
        $noche = $this->venta(['fecha_hora' => '2026-10-07 02:40:00']);
        $fin = $this->venta(['fecha_hora' => '2026-10-07 03:59:59']);
        $this->venta(['fecha_hora' => '2026-10-07 04:00:00']);
        $version = $this->servicio()->confirmar('2026-10-06', '30.00');
        $this->assertSame([$inicio, $noche, $fin], $version->detalles->pluck('venta_id')->all());
        $detalle = $version->detalles->firstWhere('venta_id', $noche);
        $this->assertSame('2026-10-06 22:40:00', $detalle->fecha_cobro->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-07 02:40:00', DB::table('cierre_caja_version_ventas')->where('id', $detalle->id)->value('fecha_cobro'));
        $this->assertSame('2026-10-06 04:00:00', $version->cierre->fechaInicio);
        $this->assertSame('2026-10-07 04:00:00', $version->cierre->fechaFin);
    }

    public function test_solo_incluye_ventas_completadas_y_acepta_qr_historico(): void
    {
        $this->venta(['estado' => 'Anulado']);
        $id = $this->venta(['metodo_pago' => 'Transferencia QR', 'monto_efectivo' => '0.00', 'monto_qr' => '10.00']);
        $version = $this->servicio()->confirmar('2026-10-06', '0.00');
        $this->assertSame([$id], $version->detalles->pluck('venta_id')->all());
        $this->assertSame('10.00', $version->qr_registrado);
    }

    public function test_dia_sin_ventas_se_registra_en_cero(): void
    {
        $this->assertSame(0, $this->servicio()->previsualizar('2026-10-06')['cantidad_ventas']);
        $version = $this->servicio()->confirmar('2026-10-06', '0');
        $this->assertSame('0.00', $version->total_ventas);
        $this->assertSame('0.00', $version->efectivo_esperado);
        $this->assertSame('0.00', $version->qr_registrado);
        $this->assertSame('0.00', $version->diferencia);
        $this->assertCount(0, $version->detalles);
    }

    public function test_no_confirma_hoy_futuro_ni_fechas_invalidas(): void
    {
        foreach (['2026-10-07', '2026-10-08', '2026-02-30', '2026-1-1', 'incorrecta'] as $fecha) {
            try {
                $this->servicio()->confirmar($fecha, '0');
                $this->fail('La fecha no debe admitirse para cierre.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('fecha', $e->errors());
            }
        }
        $this->assertSame(0, $this->servicio()->previsualizar('2026-10-07')['cantidad_ventas']);
        $this->sinCierre();
    }

    public function test_confirmacion_esta_bloqueada_hasta_certificar_concurrencia_mariadb(): void
    {
        $servicio = app(CierreDiarioService::class);
        $this->assertSame('0.00', $servicio->previsualizar('2026-10-06')['total_ventas']);
        try {
            $servicio->confirmar('2026-10-06', '0');
            $this->fail('No debe habilitarse la confirmación sin coordinación compartida.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Confirmación deshabilitada', $e->getMessage());
        }
        $this->sinCierre();
    }

    public function test_confirma_con_recalculo_en_lugar_de_la_previsualizacion(): void
    {
        $this->venta();
        $servicio = $this->servicio();
        $this->assertSame('10.00', $servicio->previsualizar('2026-10-06')['total_ventas']);
        $this->venta(['total' => '5.00']);
        $version = $servicio->confirmar('2026-10-06', '15.00');
        $this->assertSame('15.00', $version->total_ventas);
        $this->assertCount(2, $version->detalles);
    }

    public function test_doble_cierre_es_rechazado_sin_crear_otra_version(): void
    {
        $servicio = $this->servicio();
        $servicio->confirmar('2026-10-06', '0');
        try {
            $servicio->confirmar('2026-10-06', '0');
            $this->fail('No debe crear otro cierre ni convertirlo en corrección.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fecha', $e->errors());
        }
        $this->assertSame(1, CierreCaja::oficiales()->count());
        $this->assertSame(1, CierreCajaVersion::count());
    }

    public function test_efectivo_declarado_rechaza_floats_y_totales_inyectados(): void
    {
        foreach ([0.1, '-1', '1.001', '1e2', ['total_ventas' => '0', 'diferencia' => '0'], '100000000000000.00'] as $importe) {
            try {
                $this->servicio()->confirmar('2026-10-06', $importe);
                $this->fail('No debe aceptar importes manipulados o ambiguos.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('efectivo_contado', $e->errors());
            }
        }
        $this->sinCierre();
    }

    public function test_faltante_sin_observacion_no_confirma_y_sobrante_la_conserva(): void
    {
        $this->venta();
        try {
            $this->servicio()->confirmar('2026-10-06', '9.00');
            $this->fail('Debe explicar la diferencia.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('observacion', $e->errors());
        }
        $this->sinCierre();
        $version = $this->servicio()->confirmar('2026-10-06', '11.00', '  Sobrante verificado  ');
        $this->assertSame('1.00', $version->diferencia);
        $this->assertSame('Sobrante verificado', $version->observacion);
    }

    public function test_actor_se_obtiene_de_autenticacion_y_se_revalida_su_estado(): void
    {
        foreach (['sin_sesion', 'inactivo', 'rol_inactivo', 'eliminado'] as $caso) {
            DB::table('usuarios')->where('id', 1)->update(['estado' => 1, 'deleted_at' => null]);
            DB::table('roles')->where('rol_id', 1)->update(['estado' => 1]);
            $this->actingAs(User::findOrFail(1));
            match ($caso) {
                'sin_sesion' => auth()->logout(),
                'inactivo' => DB::table('usuarios')->where('id', 1)->update(['estado' => 0]),
                'rol_inactivo' => DB::table('roles')->where('rol_id', 1)->update(['estado' => 0]),
                'eliminado' => DB::table('usuarios')->where('id', 1)->update(['deleted_at' => '2026-10-07 04:00:00']),
            };
            try {
                $this->servicio()->confirmar('2026-10-06', '0');
                $this->fail('Debe rechazar el contexto de actor inválido.');
            } catch (AuthenticationException) {
                $this->sinCierre();
            }
        }
    }

    public function test_ventas_inconsistentes_bloquean_previsualizacion_y_confirmacion(): void
    {
        $id = $this->venta();
        $pedidoId = DB::table('ventas')->where('id', $id)->value('pedido_id');
        foreach ([['monto_efectivo' => '9.00'], ['monto_qr' => '-1.00'], ['total' => '11.00'],
            ['metodo_pago' => 'Tarjeta'], ['metodo_pago' => 'Mixto'], ['metodo_pago' => 'QR']] as $cambio) {
            DB::table('ventas')->where('id', $id)->update(['monto_efectivo' => '10.00', 'monto_qr' => '0.00', 'total' => '10.00', 'metodo_pago' => 'Efectivo']);
            DB::table('ventas')->where('id', $id)->update($cambio);
            foreach (['previsualizar', 'confirmar'] as $metodo) {
                try {
                    $metodo === 'previsualizar' ? $this->servicio()->previsualizar('2026-10-06')
                        : $this->servicio()->confirmar('2026-10-06', '10.00');
                    $this->fail('No debe ignorar una venta inconsistente.');
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey('ventas', $e->errors());
                }
            }
        }
        DB::table('ventas')->where('id', $id)->update(['metodo_pago' => 'Efectivo']);
        foreach ([['total' => '9.00'], ['total' => '10.00', 'estado' => 'Registrado']] as $cambioPedido) {
            DB::table('pedidos')->where('id', $pedidoId)->update($cambioPedido);
            try {
                $this->servicio()->confirmar('2026-10-06', '10.00');
                $this->fail('El pedido asociado debe ser coherente con el cobro.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('ventas', $e->errors());
            }
        }
        $this->sinCierre();
    }

    public function test_instantanea_no_depende_de_cambios_posteriores_en_ventas(): void
    {
        $id = $this->venta(['fecha_hora' => '2026-10-07 02:40:00']);
        $version = $this->servicio()->confirmar('2026-10-06', '10.00');
        DB::table('ventas')->where('id', $id)->update(['total' => '99.00', 'metodo_pago' => 'QR', 'fecha_hora' => '2026-10-08 04:00:00']);
        $detalle = $version->detalles()->firstOrFail();
        $this->assertSame('10.00', $detalle->total);
        $this->assertSame('Efectivo', $detalle->metodo_pago);
        $this->assertSame('2026-10-06 22:40:00', $detalle->fecha_cobro->format('Y-m-d H:i:s'));
        $this->assertSame('10.00', $version->fresh()->total_ventas);
    }

    public function test_fallo_en_segundo_detalle_revierte_todo(): void
    {
        $this->venta();
        $this->venta();
        $creados = 0;
        CierreCajaVersionVenta::creating(function () use (&$creados) {
            if (++$creados === 2) {
                throw new RuntimeException('Fallo de instantánea simulado');
            }
        });
        try {
            $this->servicio()->confirmar('2026-10-06', '20.00');
            $this->fail('Debe propagar el fallo y revertir la transacción.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo de instantánea simulado', $e->getMessage());
        }
        $this->sinCierre();
        $this->assertSame(2, DB::table('ventas')->count());
    }

    public function test_fallo_de_bitacora_revierte_cierre_version_y_detalles(): void
    {
        $this->venta();
        Bitacora::creating(function (Bitacora $evento) {
            if ($evento->accion === 'CIERRE_CAJA') {
                throw new RuntimeException('Fallo de auditoría simulado');
            }
        });
        try {
            $this->servicio()->confirmar('2026-10-06', '10.00');
            $this->fail('No debe conservar un cierre sin su auditoría.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo de auditoría simulado', $e->getMessage());
        }
        $this->sinCierre();
    }

    public function test_cancelacion_silenciosa_de_detalle_tambien_revierte(): void
    {
        $this->venta();
        CierreCajaVersionVenta::creating(fn () => false);
        try {
            $this->servicio()->confirmar('2026-10-06', '10.00');
            $this->fail('No debe admitir una instantánea incompleta.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('todas las ventas', $e->getMessage());
        }
        $this->sinCierre();
    }

    public function test_contrato_de_coordinacion_abarca_recalculo_y_commit(): void
    {
        $this->venta();
        $orden = [];
        $servicio = $this->servicio(function () use (&$orden) {
            $this->assertSame(0, DB::transactionLevel());
            // Modela un cobro anterior que terminó antes de adquirir exclusión.
            $this->venta();
            $orden[] = 'adquirido';
        }, function () use (&$orden) {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame(2, CierreCajaVersionVenta::count());
            $orden[] = 'liberado_tras_commit';
        });
        $version = $servicio->confirmar('2026-10-06', '20.00');
        $this->assertSame('20.00', $version->total_ventas);
        $this->assertSame(['adquirido', 'liberado_tras_commit'], $orden);
    }

    public function test_no_permite_coordinar_dentro_de_una_transaccion_abierta(): void
    {
        DB::beginTransaction();
        try {
            $this->servicio()->confirmar('2026-10-06', '0');
            $this->fail('El bloqueo debe preceder a la transacción del cierre.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('antes de iniciar', $e->getMessage());
        } finally {
            DB::rollBack();
        }
        $this->sinCierre();
    }

    public function test_sqlite_no_demuestra_exclusion_de_inserciones_por_lock_for_update(): void
    {
        $this->assertStringNotContainsString('for update', strtolower(DB::table('ventas')->lockForUpdate()->toSql()));
        // Intercalado secuencial explícito: no se presenta como concurrencia de dos conexiones.
        DB::transaction(function () {
            $anteriores = DB::table('ventas')->lockForUpdate()->get();
            $this->venta();
            $this->assertCount(0, $anteriores);
            $this->assertSame(1, DB::table('ventas')->count());
        });
        $otraConexion = new \PDO('sqlite::memory:');
        $this->assertSame(0, (int) $otraConexion->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'ventas'")->fetchColumn());
        $this->sinCierre();
    }

    public function test_historicos_null_no_se_oficializan_ni_bloquean_la_fecha(): void
    {
        $historico = CierreCaja::create(['fechaInicio' => '2026-10-06 08:00:00', 'idUsuario' => 1, 'totalSistema' => '5.00']);
        $this->servicio()->confirmar('2026-10-06', '0');
        $this->assertNull($historico->fresh()->fecha_operativa);
        $this->assertSame('5.00', $historico->fresh()->totalSistema);
        $this->assertSame(2, CierreCaja::count());
        $this->assertSame(1, CierreCaja::oficiales()->count());
    }

    public function test_concurrencia_real_cobro_cierre_requiere_integracion_y_mariadb(): void
    {
        $this->markTestSkipped('Coordinador integrado; pendiente probar cobros/cierres reales con dos conexiones MariaDB en un entorno desechable autorizado. SQLite y PDO simulado no certifican esta garantía.');
    }

    public function test_venta_con_fecha_invalida_no_se_normaliza_silenciosamente(): void
    {
        // SQLite permite este valor; MariaDB estricto debería rechazarlo al escribir.
        $this->venta(['fecha_hora' => '2026-10-06 25:00:00']);
        try {
            $this->servicio()->confirmar('2026-10-06', '10.00');
            $this->fail('No debe normalizar una fecha inválida y asociarla a otro día.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ventas', $e->errors());
        }
        $this->sinCierre();
    }

    public function test_venta_previamente_asociada_a_otro_dia_bloquea_el_cierre(): void
    {
        $venta = $this->venta();
        $anterior = CierreCaja::create(['fecha_operativa' => '2026-10-05', 'fechaInicio' => '2026-10-05 04:00:00', 'idUsuario' => 1]);
        $version = $anterior->versiones()->create([
            'numero_version' => 1, 'responsable_id' => 1, 'registrado_en' => now(),
            'total_ventas' => '10.00', 'efectivo_esperado' => '10.00', 'qr_registrado' => '0.00', 'efectivo_contado' => '10.00',
        ]);
        $version->detalles()->create([
            'venta_id' => $venta, 'fecha_cobro' => '2026-10-05 14:00:00', 'metodo_pago' => 'Efectivo',
            'total' => '10.00', 'monto_efectivo' => '10.00', 'monto_qr' => '0.00',
        ]);
        try {
            $this->servicio()->confirmar('2026-10-06', '10.00');
            $this->fail('No debe reutilizar una venta asignada a otra fecha.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ventas', $e->errors());
        }
        $this->assertSame(1, CierreCaja::count());
        $this->assertSame(1, CierreCajaVersion::count());
        $this->assertSame(1, CierreCajaVersionVenta::count());
    }

    public function test_cancelacion_silenciosa_de_bitacora_revierte_todo(): void
    {
        Bitacora::creating(fn (Bitacora $e) => $e->accion !== 'CIERRE_CAJA');
        try {
            $this->servicio()->confirmar('2026-10-06', '0');
            $this->fail('La auditoría debe existir al confirmar.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('auditoría', $e->getMessage());
        }
        $this->sinCierre();
    }

    public function test_coordinacion_se_libera_despues_del_rollback_simulado(): void
    {
        $liberado = false;
        $this->venta();
        Bitacora::creating(function (Bitacora $evento) {
            if ($evento->accion === 'CIERRE_CAJA') {
                throw new RuntimeException('Fallo antes de commit');
            }
        });
        $servicio = $this->servicio(null, function () use (&$liberado) {
            $this->sinCierre();
            $liberado = true;
        });
        try {
            $servicio->confirmar('2026-10-06', '10.00');
            $this->fail('Debe revertir y devolver el control al coordinador.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo antes de commit', $e->getMessage());
        }
        $this->assertTrue($liberado);
    }

    public function test_a_medianoche_ya_se_puede_cerrar_el_dia_anterior(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 00:00:00', 'America/La_Paz'));
        $version = $this->servicio()->confirmar('2026-10-06', '0');
        $this->assertSame('2026-10-06', $version->cierre->fecha_operativa->toDateString());
    }
}
