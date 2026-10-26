<?php

namespace Tests\Feature;

use App\Models\{CierreCaja, CierreCajaVersion, CierreCajaVersionVenta};
use Illuminate\Database\QueryException;
use Illuminate\Database\{MariaDbConnection, MySqlConnection};
use Illuminate\Support\Facades\{DB, Schema};
use LogicException;
use Tests\TestCase;

class CajaEsquemaTest extends TestCase
{
    private const MIGRACION = '2026_10_26_000001_preparar_cierres_diarios_versionados.php';

    protected function setUp(): void
    {
        parent::setUp();

        // Fallar antes de abrir una conexión si la configuración no está aislada.
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new LogicException('CajaEsquemaTest solo permite SQLite :memory: sin URL alternativa.');
        }
        $conexion = DB::connection();
        $this->assertSame('sqlite', $conexion->getDriverName());
        foreach ($conexion->select('PRAGMA database_list') as $base) {
            $this->assertSame('', $base->file, 'No se permiten bases persistentes.');
        }
        $this->assertSame(1, (int) $conexion->selectOne('PRAGMA foreign_keys')->foreign_keys);

        // Ejecutar el esquema histórico real, sin seeders ni migrate:fresh.
        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            if (basename($archivo) !== self::MIGRACION) {
                (require $archivo)->up();
            }
        }

        DB::table('roles')->insert(['rol_id' => 1, 'nombre' => 'Prueba']);
        DB::table('usuarios')->insert([
            'id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Prueba esquema',
            'nombreUsuario' => 'prueba-esquema', 'password' => 'no-es-una-credencial',
        ]);
        DB::table('pedidos')->insert([
            'id' => 1, 'usuario_id' => 1, 'fechaHora' => '2026-10-07 02:40:00',
            'total' => '24.70', 'estado' => 'Pagado',
        ]);
        DB::table('ventas')->insert([
            'id' => 1, 'pedido_id' => 1, 'usuario_id' => 1,
            'fecha_hora' => '2026-10-07 02:40:00', 'total' => '24.70',
            'metodo_pago' => 'Mixto', 'monto_efectivo' => '14.70',
            'monto_qr' => '10.00', 'monto_recibido' => '30.00',
            'cambio' => '5.30', 'estado' => 'Completado',
        ]);
        // Dos cierres históricos del mismo día deben sobrevivir sin ser oficiales.
        DB::table('cierre_cajas')->insert([
            ['id' => 1, 'idUsuario' => 1, 'fechaInicio' => '2026-10-06 08:00:00', 'totalSistema' => '12.00'],
            ['id' => 2, 'idUsuario' => 1, 'fechaInicio' => '2026-10-06 08:00:00', 'totalSistema' => '15.00'],
        ]);
        $this->migracion()->up();
    }

    private function migracion()
    {
        return require database_path('migrations/'.self::MIGRACION);
    }

    private function cierre(string $fecha = '2026-10-06'): CierreCaja
    {
        return CierreCaja::create([
            'fecha_operativa' => $fecha, 'fechaInicio' => $fecha.' 04:00:00',
            'idUsuario' => 1,
        ]);
    }

    private function version(CierreCaja $cierre, int $numero = 1, array $datos = []): CierreCajaVersion
    {
        return $cierre->versiones()->create(array_replace([
            'numero_version' => $numero, 'responsable_id' => 1,
            'registrado_en' => '2026-10-07 09:00:00',
            'total_ventas' => '24.70', 'efectivo_esperado' => '14.70',
            'qr_registrado' => '10.00', 'efectivo_contado' => '14.20',
            'motivo_correccion' => $numero > 1 ? 'Recuento autorizado' : null,
        ], $datos));
    }

    private function detalle(CierreCajaVersion $version): CierreCajaVersionVenta
    {
        return $version->detalles()->create([
            'venta_id' => 1, 'fecha_cobro' => '2026-10-06 22:40:00',
            'metodo_pago' => 'Mixto', 'total' => '24.70',
            'monto_efectivo' => '14.70', 'monto_qr' => '10.00',
        ]);
    }

    public function test_migracion_preserva_historicos_sin_oficializarlos(): void
    {
        $this->assertSame(2, CierreCaja::count());
        $this->assertSame(0, CierreCaja::oficiales()->count());
        $this->assertSame('12.00', CierreCaja::findOrFail(1)->totalSistema);
        $this->assertSame('15.00', CierreCaja::findOrFail(2)->totalSistema);
        $this->assertSame(0, CierreCajaVersion::count());
        $this->assertNull(CierreCaja::findOrFail(1)->versionVigente);
        $this->assertSame(1, $this->cierre()->usuario->id);
        $this->assertSame(1, CierreCaja::oficiales()->count());
    }

    public function test_fecha_oficial_no_se_puede_duplicar(): void
    {
        $this->cierre();
        $this->expectException(QueryException::class);
        $this->cierre();
    }

    public function test_version_vigente_es_el_mayor_numero_y_conserva_anteriores(): void
    {
        $cierre = $this->cierre();
        $segunda = $this->version($cierre, 2, ['efectivo_contado' => '14.70']);
        $primera = $this->version($cierre);
        $this->assertSame($segunda->id, $cierre->fresh()->versionVigente->id);
        $this->assertSame(2, $cierre->versiones()->count());
        $this->assertSame('-0.50', $primera->fresh()->diferencia);
        $this->assertSame('0.00', $segunda->fresh()->diferencia);
        $this->assertSame($cierre->id, $primera->cierre->id);
        $this->assertSame(1, $primera->responsable->id);
    }

    public function test_numero_version_es_unico_dentro_de_cada_cierre(): void
    {
        $this->version($this->cierre());
        $this->version($this->cierre('2026-10-15'));
        $this->expectException(QueryException::class);
        $this->version(CierreCaja::oficiales()->whereDate('fecha_operativa', '2026-10-06')->firstOrFail());
    }

    public function test_diferencia_se_calcula_en_base_y_decimales_se_exponen_como_cadenas(): void
    {
        $version = $this->version($this->cierre(), 1, [
            'total_ventas' => '1000000000.30', 'efectivo_esperado' => '1000000000.30',
            'qr_registrado' => '0.00', 'efectivo_contado' => '1000000000.10',
        ])->fresh();
        $this->assertSame('1000000000.30', $version->total_ventas);
        $this->assertSame('-0.20', $version->diferencia);
        $this->expectException(QueryException::class);
        DB::table('cierre_caja_versiones')->where('id', $version->id)->update(['diferencia' => '999.00']);
    }

    public function test_venta_puede_repetirse_en_otra_version_con_instantanea_utc(): void
    {
        $cierre = $this->cierre();
        $primera = $this->version($cierre);
        $detalle = $this->detalle($primera);
        $this->detalle($this->version($cierre, 2));
        $this->assertSame(2, CierreCajaVersionVenta::count());
        $this->assertSame('2026-10-07 02:40:00', DB::table('cierre_caja_version_ventas')->where('id', $detalle->id)->value('fecha_cobro'));
        $this->assertSame('2026-10-06 22:40:00', $detalle->fresh()->fecha_cobro->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-07 13:00:00', DB::table('cierre_caja_versiones')->where('id', $primera->id)->value('registrado_en'));
        $this->assertSame(1, $detalle->venta->id);
        $this->assertSame($primera->id, $detalle->version->id);
        DB::table('ventas')->where('id', 1)->update(['total' => '99.00']);
        $this->assertSame('24.70', $detalle->fresh()->total);
    }

    public function test_no_se_duplica_venta_dentro_de_una_version(): void
    {
        $version = $this->version($this->cierre());
        $this->detalle($version);
        $this->expectException(QueryException::class);
        $this->detalle($version);
    }

    public function test_clave_foranea_rechaza_venta_inexistente(): void
    {
        $version = $this->version($this->cierre());
        $this->detalle($version);
        $this->expectException(QueryException::class);
        DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $version->id)->update(['venta_id' => 999]);
    }

    public function test_no_se_puede_eliminar_fisicamente_una_venta_incluida(): void
    {
        $this->detalle($this->version($this->cierre()));
        $this->expectException(QueryException::class);
        DB::table('ventas')->where('id', 1)->delete();
    }

    public function test_no_se_puede_eliminar_un_cierre_con_versiones(): void
    {
        $cierre = $this->cierre();
        $this->version($cierre);
        $this->expectException(QueryException::class);
        DB::table('cierre_cajas')->where('id', $cierre->id)->delete();
    }

    public function test_responsable_borrado_logicamente_sigue_disponible(): void
    {
        $cierre = $this->cierre();
        $version = $this->version($cierre);
        DB::table('usuarios')->where('id', 1)->update(['deleted_at' => '2026-10-08 04:00:00']);
        $this->assertTrue($cierre->fresh()->usuario->trashed());
        $this->assertTrue($version->fresh()->responsable->trashed());
        $this->expectException(QueryException::class);
        DB::table('usuarios')->where('id', 1)->delete();
    }

    public function test_responsable_historico_no_se_elimina_en_cascada(): void
    {
        // Este usuario no tiene ventas/pedidos: solo la FK del cierre puede impedir el borrado.
        DB::table('usuarios')->insert([
            'id' => 2, 'rol_id' => 1, 'nombreCompleto' => 'Responsable histórico',
            'nombreUsuario' => 'historico-esquema', 'password' => 'no-es-una-credencial',
        ]);
        DB::table('cierre_cajas')->where('id', 1)->update(['idUsuario' => 2]);
        $this->expectException(QueryException::class);
        DB::table('usuarios')->where('id', 2)->delete();
    }

    public function test_version_y_detalle_rechazan_edicion_y_eliminacion_por_modelo(): void
    {
        $version = $this->version($this->cierre());
        $detalle = $this->detalle($version);
        foreach ([$version->cierre, $version, $detalle] as $modelo) {
            foreach (['save', 'delete', 'saveQuietly', 'deleteQuietly'] as $operacion) {
                try {
                    if (in_array($operacion, ['save', 'saveQuietly'], true)) {
                        $modelo->setAttribute($modelo instanceof CierreCajaVersionVenta ? 'metodo_pago' : 'observacion', 'Cambio');
                    }
                    $modelo->{$operacion}();
                    $this->fail('La historia no debe poder modificarse por modelo.');
                } catch (LogicException $e) {
                    $this->assertNotSame('', $e->getMessage());
                }
            }
        }
    }

    public function test_rollback_estructural_preserva_cierres_historicos_y_puede_reaplicarse(): void
    {
        DB::table('usuarios')->insert([
            'id' => 2, 'rol_id' => 1, 'nombreCompleto' => 'Responsable histórico',
            'nombreUsuario' => 'historico-esquema', 'password' => 'no-es-una-credencial',
        ]);
        DB::table('cierre_cajas')->where('id', 2)->update(['idUsuario' => 2]);
        $this->migracion()->down();
        $this->assertFalse(Schema::hasTable('cierre_caja_versiones'));
        $this->assertFalse(Schema::hasTable('cierre_caja_version_ventas'));
        $this->assertFalse(Schema::hasColumn('cierre_cajas', 'fecha_operativa'));
        $this->assertSame(2, DB::table('cierre_cajas')->count());
        $this->assertSame('12', (string) DB::table('cierre_cajas')->where('id', 1)->value('totalSistema'));
        $this->migracion()->up();
        $this->assertSame(0, CierreCaja::oficiales()->count());
        $this->assertSame(2, CierreCaja::count());
        $this->migracion()->down();
        // Restaurar el comportamiento histórico CASCADE solo al revertir el esquema vacío.
        DB::table('usuarios')->where('id', 2)->delete();
        $this->assertSame(1, DB::table('cierre_cajas')->count());
    }

    public function test_incrementos_individuales_no_pueden_reescribir_importes(): void
    {
        $version = $this->version($this->cierre());
        $detalle = $this->detalle($version);
        foreach ([$version->cierre, $version, $detalle] as $modelo) {
            $columna = $modelo instanceof CierreCajaVersion ? 'efectivo_contado'
                : ($modelo instanceof CierreCajaVersionVenta ? 'total' : 'totalSistema');
            $valorOriginal = $modelo->fresh()->getAttribute($columna);
            foreach (['increment', 'decrement', 'incrementQuietly', 'decrementQuietly',
                'incrementEach', 'decrementEach', 'incrementEachQuietly', 'decrementEachQuietly'] as $metodo) {
                try {
                    if (str_contains($metodo, 'Each')) {
                        $modelo->{$metodo}([$columna => 1]);
                    } else {
                        $modelo->{$metodo}($columna, 1);
                    }
                    $this->fail('Los incrementos no deben alterar un registro financiero.');
                } catch (LogicException $e) {
                    $this->assertSame($valorOriginal, $modelo->fresh()->getAttribute($columna));
                }
            }
        }
    }

    public function test_rollback_con_historia_se_rechaza_antes_de_cualquier_cambio(): void
    {
        $version = $this->version($this->cierre());
        $detalle = $this->detalle($version);
        try {
            $this->migracion()->down();
            $this->fail('El rollback no debe destruir historia financiera.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('registros financieros', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('cierre_cajas', 'fecha_operativa'));
        $this->assertSame('24.70', $version->fresh()->total_ventas);
        $this->assertSame('24.70', $detalle->fresh()->total);
        $this->assertSame(3, CierreCaja::count());
    }

    public function test_rollback_con_fecha_oficial_sin_version_tambien_se_rechaza(): void
    {
        $this->cierre();
        try {
            $this->migracion()->down();
            $this->fail('No se debe perder la fecha de un cierre oficial.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('fechas operativas', $e->getMessage());
        }
        $this->assertTrue(Schema::hasTable('cierre_caja_versiones'));
        $this->assertTrue(Schema::hasTable('cierre_caja_version_ventas'));
        $this->assertSame(1, CierreCaja::oficiales()->count());
    }

    public function test_version_con_ventas_no_se_elimina_por_sql(): void
    {
        $version = $this->version($this->cierre());
        $this->detalle($version);
        $this->expectException(QueryException::class);
        DB::table('cierre_caja_versiones')->where('id', $version->id)->delete();
    }

    public function test_fk_de_responsable_de_version_es_independiente_de_la_cabecera(): void
    {
        DB::table('usuarios')->insert([
            'id' => 2, 'rol_id' => 1, 'nombreCompleto' => 'Responsable de corrección',
            'nombreUsuario' => 'correccion-esquema', 'password' => 'no-es-una-credencial',
        ]);
        $this->version($this->cierre(), 1, ['responsable_id' => 2]);
        $this->expectException(QueryException::class);
        DB::table('usuarios')->where('id', 2)->delete();
    }

    public function test_sql_mariadb_y_mysql_se_compila_sin_conexion_real(): void
    {
        $esquemaSqlite = Schema::getFacadeRoot();
        try {
            // El resolver falla si el compilador intenta abrir siquiera un PDO.
            foreach ([MariaDbConnection::class, MySqlConnection::class] as $clase) {
                $conexion = $this->getMockBuilder($clase)
                    ->setConstructorArgs([function () {
                        throw new LogicException('La compilación SQL no puede abrir conexiones.');
                    }, 'compilacion_sin_base', '', [
                        'driver' => $clase === MariaDbConnection::class ? 'mariadb' : 'mysql',
                        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                    ]])
                    ->onlyMethods(['isMaria', 'getServerVersion'])
                    ->getMock();
                $conexion->expects($this->atLeastOnce())->method('isMaria')->willReturn(true);
                $conexion->expects($this->atLeastOnce())->method('getServerVersion')->willReturn('10.4.32');
                Schema::swap($conexion->getSchemaBuilder());
                $sentencias = array_column($conexion->pretend(fn () => $this->migracion()->up()), 'query');
                $sql = implode("\n", $sentencias);
                $this->assertStringContainsString('`diferencia` decimal(17, 2) as (efectivo_contado - efectivo_esperado) stored', $sql);
                $this->assertStringContainsString('engine = InnoDB', $sql);
                $this->assertStringContainsString('`fecha_operativa` date null', $sql);
                $this->assertStringContainsString('unique `cierre_cajas_fecha_operativa_unique`', $sql);
                $this->assertStringContainsString('unique `caja_version_numero_unique`', $sql);
                $this->assertStringContainsString('unique `caja_version_venta_unique`', $sql);
                $cambiosFk = array_values(array_filter($sentencias, fn ($s) => str_contains($s, 'drop foreign key')));
                $this->assertCount(1, $cambiosFk);
                $this->assertStringContainsString('add constraint `cierre_cajas_responsable_restrict_foreign`', $cambiosFk[0]);
                $this->assertStringContainsString('on delete restrict', $cambiosFk[0]);

                // Pretend devuelve vacío para las consultas de seguridad: solo se compila el DDL.
                $sqlDown = implode("\n", array_column($conexion->pretend(fn () => $this->migracion()->down()), 'query'));
                $this->assertStringContainsString('drop foreign key `cierre_cajas_responsable_restrict_foreign`, add constraint `cierre_cajas_idusuario_foreign`', $sqlDown);
                $this->assertStringContainsString('on delete cascade', $sqlDown);
            }
        } finally {
            Schema::swap($esquemaSqlite);
        }
    }
}
