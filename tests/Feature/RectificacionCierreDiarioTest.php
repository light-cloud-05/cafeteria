<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{Bitacora, CierreCaja, CierreCajaVersion, CierreCajaVersionVenta, User};
use App\Services\Caja\{AutorizacionCaja, CierreDiarioService, CoordinacionCobrosCaja, CoordinadorCobrosCaja,
    FalloCoordinacionCaja, RectificacionCierreDiarioService};
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class RectificacionCierreDiarioTest extends TestCase
{
    private int $cierreId;
    private int $baseId;
    private array $permisos = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new LogicException('Solo se permite SQLite en memoria.');
        }
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach (DB::select('PRAGMA database_list') as $base) {
            $this->assertSame('', $base->file);
        }
        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            (require $archivo)->up();
        }
        DB::table('roles')->insert([
            ['rol_id' => 1, 'nombre' => 'Responsable inicial', 'estado' => 1],
            ['rol_id' => 2, 'nombre' => 'Supervisor explícito', 'estado' => 1],
        ]);
        foreach ([1, 2] as $id) {
            DB::table('usuarios')->insert(['id' => $id, 'rol_id' => $id, 'nombreCompleto' => 'Actor sintético '.$id,
                'nombreUsuario' => 'rectificacion-'.$id, 'password' => 'no-es-una-credencial', 'estado' => 1]);
        }
        foreach ([AutorizacionCaja::CONSULTAR, AutorizacionCaja::CONFIRMAR, AutorizacionCaja::RECTIFICAR] as $nombre) {
            $id = $this->permisos[$nombre] = DB::table('permisos')->insertGetId(['nombre' => $nombre]);
            DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $id]);
            if ($nombre !== AutorizacionCaja::CONFIRMAR) {
                DB::table('rol_permisos')->insert(['idRol' => 2, 'idPermiso' => $id]);
            }
        }
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 12:00:00', 'America/La_Paz'));
        $this->actingAs(User::findOrFail(1));
        foreach ([['10.10', '10.10', '0.00', 'Efectivo', '2026-10-06 18:00:00'],
            ['20.20', '5.20', '15.00', 'Mixto', '2026-10-07 02:40:00']] as [$total, $efectivo, $qr, $metodo, $fecha]) {
            $pedido = DB::table('pedidos')->insertGetId(['usuario_id' => 1, 'fechaHora' => $fecha, 'total' => $total, 'estado' => 'Pagado']);
            DB::table('ventas')->insert(['pedido_id' => $pedido, 'usuario_id' => 1, 'fecha_hora' => $fecha,
                'total' => $total, 'monto_efectivo' => $efectivo, 'monto_qr' => $qr, 'metodo_pago' => $metodo,
                'monto_recibido' => $total, 'cambio' => '0.00', 'estado' => 'Completado']);
        }
        $base = (new CierreDiarioService($this->coordinador()))->confirmar('2026-10-06', '15.30', 'Cierre inicial');
        $this->cierreId = $base->cierre_caja_id;
        $this->baseId = $base->id;
        $this->actingAs(User::findOrFail(2));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function coordinador(?Closure $antes = null, ?Closure $despues = null): CoordinacionCobrosCaja
    {
        return new class($antes, $despues) implements CoordinacionCobrosCaja {
            public function __construct(private ?Closure $antes, private ?Closure $despues) {}
            public function permiteCierreDefinitivo(): bool { return true; }
            public function exclusivo(Closure $operacion): mixed
            {
                if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
                    throw new LogicException('Doble exclusivo de pruebas en memoria.');
                }
                ($this->antes ?? static fn () => null)();
                $resultado = (new CoordinadorCobrosCaja)->exclusivo($operacion);
                ($this->despues ?? static fn () => null)();
                return $resultado;
            }
        };
    }

    private function servicio(?Closure $antes = null, ?Closure $despues = null): RectificacionCierreDiarioService
    {
        return new RectificacionCierreDiarioService($this->coordinador($antes, $despues));
    }

    private function eventos()
    {
        return Bitacora::where('accion', 'like', 'RECTIFICACION_CAJA%')->orderBy('id')->get();
    }

    private function ultimoEvento(): array
    {
        return json_decode($this->eventos()->last()->valoresNuevos, true, flags: JSON_THROW_ON_ERROR);
    }

    private function soloInicial(): void
    {
        $this->assertSame(1, CierreCaja::count());
        $this->assertSame(1, CierreCajaVersion::count());
        $this->assertSame(2, CierreCajaVersionVenta::count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_rectifica_con_precision_sin_cambiar_cabecera_ventas_pedidos_o_instantaneas_previas(): void
    {
        $antes = [];
        foreach (['cierre_cajas', 'ventas', 'pedidos', 'stocks', 'productos', 'movimiento_inventarios'] as $tabla) {
            $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        }
        $versionAntes = DB::table('cierre_caja_versiones')->where('id', $this->baseId)->first();
        $detalleAntes = DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $this->baseId)->orderBy('venta_id')->get()->toJson();
        $nueva = $this->servicio()->rectificar($this->cierreId, 1, '14.29', 'Recuento supervisado', 'Nuevo conteo');
        $this->assertSame(2, $nueva->numero_version);
        $this->assertSame(2, $nueva->responsable_id);
        $this->assertSame('-1.01', $nueva->diferencia);
        $this->assertSame('30.30', $nueva->total_ventas);
        $this->assertSame('15.30', $nueva->efectivo_esperado);
        $this->assertSame('15.00', $nueva->qr_registrado);
        $this->assertSame('Recuento supervisado', $nueva->motivo_correccion);
        $this->assertSame('Nuevo conteo', $nueva->observacion);
        $this->assertSame($nueva->id, CierreCaja::findOrFail($this->cierreId)->versionVigente->id);
        foreach ($antes as $tabla => $contenido) {
            $this->assertSame($contenido, DB::table($tabla)->orderBy('id')->get()->toJson());
        }
        $this->assertEquals($versionAntes, DB::table('cierre_caja_versiones')->where('id', $this->baseId)->first());
        $this->assertSame($detalleAntes, DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $this->baseId)->orderBy('venta_id')->get()->toJson());
        $columnas = ['venta_id', 'fecha_cobro', 'metodo_pago', 'total', 'monto_efectivo', 'monto_qr'];
        $this->assertEquals(DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $this->baseId)->orderBy('venta_id')->get($columnas),
            DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $nueva->id)->orderBy('venta_id')->get($columnas));
        $this->assertSame(1, $this->eventos()->count());
        $evento = $this->ultimoEvento();
        $this->assertSame('CONFIRMADA', $evento['resultado']);
        $this->assertSame(2, $evento['actor_id']);
        $this->assertSame('2026-10-06', $evento['fecha_operativa']);
        $this->assertSame('-1.01', $evento['diferencia']);
        $this->assertSame('Recuento supervisado', $evento['motivo']);
        $this->assertStringNotContainsString('Nuevo conteo', $this->eventos()->toJson()); // Observaciones no duplicadas en auditoría.
    }

    public function test_no_recalcula_desde_ventas_o_precios_actuales(): void
    {
        DB::table('ventas')->update(['total' => '99.00']); // Solo fixture: simula una alteración posterior externa.
        DB::connection()->enableQueryLog();
        DB::flushQueryLog();
        $nueva = $this->servicio()->rectificar($this->cierreId, 1, '15.30', 'Aclaración de observación', 'Nota corregida');
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        $this->assertStringNotContainsString('from "ventas"', $sql);
        $this->assertStringNotContainsString('from "pedidos"', $sql);
        $this->assertSame('30.30', $nueva->total_ventas);
    }

    public function test_dos_intentos_sobre_version_uno_no_producen_dos_rectificaciones(): void
    {
        $segunda = null;
        $servicio = $this->servicio(function () use (&$segunda) {
            // Intercalado durante la espera: otra invocación termina antes de adquirir el primer mutex.
            $segunda = $this->servicio()->rectificar($this->cierreId, 1, '14.30', 'Recuento concurrente');
        });
        try {
            $servicio->rectificar($this->cierreId, 1, '13.30', 'Intento sobre vista obsoleta');
            $this->fail('La versión esperada obsoleta debe rechazarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('version_esperada', $e->errors());
        }
        $this->assertSame(2, CierreCajaVersion::count());
        $this->assertSame($segunda->id, CierreCaja::findOrFail($this->cierreId)->versionVigente->id);
        $this->assertSame(['RECTIFICACION_CAJA', 'RECTIFICACION_CAJA_RECHAZADA'], $this->eventos()->pluck('accion')->all());
        $this->assertSame('ROLLBACK_CONFIRMADO', $this->ultimoEvento()['persistencia']);
        $this->assertSame(1, $this->ultimoEvento()['version_esperada']);
        $this->assertSame(2, $this->ultimoEvento()['version_base']['numero_version']);
    }

    public function test_falta_de_permiso_no_lee_importes_y_audita_el_intento_sin_id_financiero_inventado(): void
    {
        DB::table('rol_permisos')->where('idRol', 2)->where('idPermiso', $this->permisos[AutorizacionCaja::CONSULTAR])->delete();
        DB::connection()->enableQueryLog(); DB::flushQueryLog();
        try {
            $this->servicio()->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('No debe permitir rectificar sin consultar.');
        } catch (AuthorizationException) {
            $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
            $this->assertStringNotContainsString('from "cierre_cajas"', $sql);
            $this->assertStringNotContainsString('from "cierre_caja_versiones"', $sql);
        }
        $evento = $this->eventos()->sole();
        $this->assertSame('usuarios', $evento->tablaAfectada);
        $this->assertSame(2, $evento->idRegistroAfectado);
        $this->assertSame('NO_INICIADA', $this->ultimoEvento()['persistencia']);
        $this->assertNull($this->ultimoEvento()['fecha_operativa']);
        $this->soloInicial();
    }

    public function test_permiso_revocado_durante_espera_no_crea_version(): void
    {
        $servicio = $this->servicio(fn () => DB::table('rol_permisos')->where('idRol', 2)
            ->where('idPermiso', $this->permisos[AutorizacionCaja::RECTIFICAR])->delete());
        try {
            $servicio->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('No debe continuar con permiso revocado.');
        } catch (AuthorizationException) {
            $this->assertSame('RECHAZADA', $this->ultimoEvento()['resultado']);
        }
        $this->soloInicial();
    }

    public function test_motivo_obligatorio_importes_exactos_y_filtrado_de_entradas_sensibles(): void
    {
        $casos = [
            ['14.30', '', null, 'motivo'], ['14.30', '  ', null, 'motivo'],
            ['14.30', str_repeat('x', 1001), null, 'motivo'], ['14.30', "\xFF", null, 'motivo'],
            ['14.30', 'password=SECRETO_NO_GUARDAR', null, 'motivo'],
            ['14.30', 'Recuento', 'token=SECRETO_NO_GUARDAR', 'observacion'],
            [14.30, 'Recuento', null, 'efectivo_contado'], ['-1', 'Recuento', null, 'efectivo_contado'],
        ];
        foreach ($casos as [$monto, $motivo, $observacion, $campo]) {
            try {
                $this->servicio()->rectificar($this->cierreId, 1, $monto, $motivo, $observacion);
                $this->fail('Debe rechazar la entrada inválida.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($campo, $e->errors());
            }
        }
        $this->assertCount(count($casos), $this->eventos());
        $this->assertStringNotContainsString('SECRETO_NO_GUARDAR', $this->eventos()->toJson());
        $this->soloInicial();
    }

    public function test_rechaza_historicos_no_oficiales_y_rectificaciones_sin_cambios(): void
    {
        $historico = CierreCaja::create(['fechaInicio' => '2026-10-06 08:00:00', 'idUsuario' => 1]);
        foreach ([[$historico->id, '14.30', null, 'cierre'], [$this->cierreId, '15.30', 'Cierre inicial', 'rectificacion']] as [$id, $monto, $obs, $campo]) {
            try {
                $this->servicio()->rectificar($id, 1, $monto, 'Revisión', $obs);
                $this->fail('No debe oficializar históricos ni crear versiones sin cambios.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($campo, $e->errors());
            }
        }
        $this->assertNull($historico->fresh()->fecha_operativa);
        $this->assertSame(1, CierreCajaVersion::count());
    }

    public function test_fallo_en_una_instantanea_revierte_y_audita_rollback_confirmado(): void
    {
        $veces = 0;
        CierreCajaVersionVenta::creating(function () use (&$veces) {
            if (++$veces === 2) { throw new RuntimeException('Fallo de detalle; password=NO_PUBLICAR'); }
        });
        try {
            $this->servicio()->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('Debe revertir la versión y su primer detalle.');
        } catch (RuntimeException) {
            $this->assertSame('ROLLBACK_CONFIRMADO', $this->ultimoEvento()['persistencia']);
        }
        $this->assertSame(['RECTIFICACION_CAJA_FALLIDA'], $this->eventos()->pluck('accion')->all());
        $this->assertStringNotContainsString('NO_PUBLICAR', $this->eventos()->toJson());
        $this->soloInicial();
    }

    public function test_fallo_en_auditoria_confirmada_no_deja_rectificacion_sin_evento(): void
    {
        Bitacora::creating(function (Bitacora $evento) {
            if ($evento->accion === 'RECTIFICACION_CAJA') { throw new RuntimeException('Fallo de bitácora'); }
        });
        try {
            $this->servicio()->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('No debe conservar un cambio financiero sin su auditoría.');
        } catch (RuntimeException) {
            $this->assertSame('FALLIDA', $this->ultimoEvento()['resultado']);
        }
        $this->soloInicial();
        $this->assertSame(1, $this->eventos()->count());
    }

    public function test_fallo_de_liberacion_tras_commit_no_se_etiqueta_como_rollback(): void
    {
        $servicio = $this->servicio(null, static fn () => throw new FalloCoordinacionCaja('password=NO_PUBLICAR', true));
        try {
            $servicio->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('Debe propagar el resultado de coordinación incierto.');
        } catch (FalloCoordinacionCaja) {
            $this->assertSame('INCIERTO', $this->ultimoEvento()['resultado']);
        }
        $this->assertSame('COMMIT_CONFIRMADO', $this->ultimoEvento()['persistencia']);
        $this->assertSame(2, CierreCajaVersion::count());
        $this->assertSame(['RECTIFICACION_CAJA', 'RECTIFICACION_CAJA_INCIERTO'], $this->eventos()->pluck('accion')->all());
        $payloads = $this->eventos()->map(fn ($e) => json_decode($e->valoresNuevos, true));
        $this->assertSame($payloads[0]['operacion_id'], $payloads[1]['operacion_id']);
        $this->assertNotSame($payloads[0]['evento_id'], $payloads[1]['evento_id']);
        $this->assertStringNotContainsString('NO_PUBLICAR', $this->eventos()->toJson());
    }

    public function test_error_al_notificar_commit_no_demuestra_rollback_por_nivel_cero(): void
    {
        CierreCajaVersion::created(static fn () => DB::afterCommit(static fn () => throw new RuntimeException('Error después de commit')));
        try {
            $this->servicio()->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('Debe distinguir error de notificación de un rollback.');
        } catch (RuntimeException) {
            $this->assertSame('INCIERTO', $this->ultimoEvento()['resultado']);
        }
        $this->assertSame('INCIERTA', $this->ultimoEvento()['persistencia']);
        $this->assertSame(2, CierreCajaVersion::count());
    }

    public function test_sin_actor_respalda_incidente_sin_inventar_usuario(): void
    {
        auth()->logout(); Log::spy();
        try {
            $this->servicio()->rectificar($this->cierreId, 1, '14.30', 'password=NO_PUBLICAR');
            $this->fail('Se requiere autenticación.');
        } catch (AuthenticationException) {
            $this->assertCount(0, $this->eventos());
        }
        Log::shouldHaveReceived('warning')->once()->with('caja.rectificacion.incidente', \Mockery::on(
            fn ($c) => $c['actor_id'] === null && $c['resultado'] === 'RECHAZADA' && $c['motivo'] === null));
        $this->soloInicial();
    }

    public function test_fallo_del_registro_de_incidente_no_enmascara_el_rechazo_original(): void
    {
        Log::spy();
        DB::table('rol_permisos')->where('idRol', 2)->delete();
        Bitacora::creating(static fn (Bitacora $e) => !str_starts_with($e->accion, 'RECTIFICACION_CAJA'));
        try {
            $this->servicio()->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('Se requiere permiso.');
        } catch (AuthorizationException) {
            $this->assertCount(0, $this->eventos());
        }
        Log::shouldHaveReceived('warning')->once();
        $this->soloInicial();
    }

    public function test_sesion_descartada_no_se_reconecta_para_escribir_incidente(): void
    {
        Log::spy();
        $servicio = $this->servicio(null, static function () {
            DB::disconnect();
            throw new FalloCoordinacionCaja('Sesión descartada', true);
        });
        try {
            $servicio->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('Debe propagar el resultado incierto.');
        } catch (FalloCoordinacionCaja) {
            $this->assertNull(DB::connection()->getRawPdo());
        }
        Log::shouldHaveReceived('warning')->once()->with('caja.rectificacion.incidente', \Mockery::on(
            fn ($c) => $c['resultado'] === 'INCIERTO' && $c['persistencia'] === 'COMMIT_CONFIRMADO'));
    }

    public function test_gate_real_no_habilita_rectificaciones_y_las_rutas_siguen_sin_publicarse(): void
    {
        try {
            (new RectificacionCierreDiarioService)->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('El gate de producción debe mantenerse cerrado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('caja', $e->errors());
        }
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
        $acciones = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => $r->getActionName());
        $this->assertFalse($acciones->contains(fn ($a) => str_contains($a, 'CierreCajasController')));
        $this->soloInicial();
    }

    public function test_operaciones_genericas_eloquent_no_reescriben_ni_eliminan_historial(): void
    {
        foreach ([CierreCaja::class, CierreCajaVersion::class, CierreCajaVersionVenta::class, Bitacora::class] as $clase) {
            foreach (['update', 'delete', 'forceDelete', 'upsert', 'increment', 'decrement', 'truncate', 'updateOrInsert'] as $metodo) {
                try {
                    $q = $clase::query();
                    match ($metodo) {
                        'update' => $q->update(['updated_at' => now()]),
                        'upsert' => $q->upsert([['id' => 1]], ['id']),
                        'increment', 'decrement' => $q->{$metodo}('id'),
                        'updateOrInsert' => $q->updateOrInsert(['id' => 1], ['updated_at' => now()]),
                        default => $q->{$metodo}(),
                    };
                    $this->fail('No debe permitir reescrituras ni eliminación genérica.');
                } catch (LogicException) {
                    $this->assertTrue(true);
                }
            }
        }
        $evento = Bitacora::firstOrFail();
        try {
            $evento->forceFill(['accion' => 'BORRADO'])->saveQuietly();
            $this->fail('Tampoco se puede sobrescribir silenciosamente la bitácora.');
        } catch (LogicException) {
            $this->assertSame('CIERRE_CAJA', $evento->fresh()->accion);
        }
        $this->soloInicial();
    }

    public function test_historial_inconsistente_bloquea_la_rectificacion_sin_reescribirlo(): void
    {
        // Alteración deliberada exclusivamente del fixture SQLite: nunca reparar recalculando ventas vivas.
        DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $this->baseId)
            ->update(['fecha_cobro' => '2026-10-08 04:00:00']);
        $antes = DB::table('cierre_caja_version_ventas')->orderBy('id')->get()->toJson();
        try {
            $this->servicio()->rectificar($this->cierreId, 1, '14.30', 'Recuento');
            $this->fail('Las instantáneas ajenas al día deben bloquear la operación.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cierre', $e->errors());
        }
        $this->assertSame($antes, DB::table('cierre_caja_version_ventas')->orderBy('id')->get()->toJson());
        $this->assertSame('ROLLBACK_CONFIRMADO', $this->ultimoEvento()['persistencia']);
        $this->soloInicial();
    }

    public function test_rectificacion_de_dia_sin_ventas_conserva_importes_cero_y_detalles_vacios(): void
    {
        $this->actingAs(User::findOrFail(1));
        $inicial = (new CierreDiarioService($this->coordinador()))->confirmar('2026-10-05', '0.00');
        $this->actingAs(User::findOrFail(2));
        $nueva = $this->servicio()->rectificar($inicial->cierre_caja_id, 1, '0.01', 'Moneda encontrada');
        $this->assertSame('0.00', $nueva->total_ventas);
        $this->assertSame('0.00', $nueva->efectivo_esperado);
        $this->assertSame('0.00', $nueva->qr_registrado);
        $this->assertSame('0.01', $nueva->diferencia);
        $this->assertCount(0, $nueva->detalles);
        $this->assertSame('0.00', $inicial->fresh()->efectivo_contado);
        $this->assertSame(1, $this->eventos()->count());
    }
}
