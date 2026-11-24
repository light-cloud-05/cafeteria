<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\{MariaDbConnection, MySqlConnection, QueryException};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PagosEsquemaTest extends TestCase
{
    private const COMPONENTES = '2026_11_23_000001_agregar_tarjeta_y_origen_a_ventas_y_caja.php';
    private const INTENTOS = '2026_11_23_000002_crear_transacciones_pago.php';

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) throw new LogicException('Esquema CU26 solo en memoria.');
        foreach (DB::select('PRAGMA database_list') as $base) $this->assertSame('', $base->file);
        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            if (basename($archivo) < self::COMPONENTES) (require $archivo)->up();
        }
        DB::table('roles')->insert(['rol_id' => 1, 'nombre' => 'Prueba esquema']);
        DB::table('usuarios')->insert(['id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Fixture',
            'nombreUsuario' => 'esquema-cu26', 'password' => 'no-es-credencial']);
        DB::table('pedidos')->insert(['id' => 1, 'usuario_id' => 1, 'fechaHora' => '2026-10-01 16:00:00',
            'total' => '24.70', 'estado' => 'Pagado']);
        DB::table('ventas')->insert(['id' => 1, 'pedido_id' => 1, 'usuario_id' => 1,
            'fecha_hora' => '2026-10-01 16:00:00', 'total' => '24.70', 'metodo_pago' => 'Mixto',
            'monto_efectivo' => '14.70', 'monto_qr' => '10.00', 'monto_recibido' => '30.00', 'cambio' => '5.30', 'estado' => 'Completado']);
        $this->migracion(self::COMPONENTES)->up();
        $this->migracion(self::INTENTOS)->up();
    }

    private function migracion(string $nombre) { return require database_path('migrations/'.$nombre); }

    public function test_ampliacion_preserva_importes_historicos_y_agrega_cero_tarjeta(): void
    {
        $venta = DB::table('ventas')->first();
        $this->assertEquals('24.70', $venta->total);
        $this->assertEquals('14.70', $venta->monto_efectivo);
        $this->assertEquals('10.00', $venta->monto_qr);
        $this->assertEquals('5.30', $venta->cambio);
        $this->assertEquals(0, $venta->monto_tarjeta);
        $this->assertSame('OPERACION', $venta->origen);
        $this->assertSame(0, DB::table('transacciones_pago')->count());
    }

    private function datos(): array
    {
        return ['pedido_id' => 1, 'usuario_id' => 1, 'referencia' => (string)Str::uuid(),
            'clave_idempotencia' => (string)Str::uuid(), 'medio' => 'QR_SIMULADO', 'monto' => '24.70',
            'estado' => 'PENDIENTE', 'version_pedido' => str_repeat('a',64), 'vence_en' => '2026-11-23 16:00:00'];
    }

    public function test_estado_desconocido_es_rechazado_por_la_base(): void
    {
        $this->expectException(QueryException::class);
        DB::table('transacciones_pago')->insert(array_replace($this->datos(), ['estado' => 'DESCONOCIDO']));
    }

    public function test_una_venta_no_puede_asociarse_a_dos_intentos(): void
    {
        DB::table('transacciones_pago')->insert(array_replace($this->datos(), ['estado' => 'APROBADO', 'venta_id' => 1]));
        $this->expectException(QueryException::class);
        DB::table('transacciones_pago')->insert(array_replace($this->datos(), ['estado' => 'APROBADO', 'venta_id' => 1]));
    }

    public function test_rollback_no_borra_historia_de_intentos(): void
    {
        DB::table('transacciones_pago')->insert($this->datos());
        try {
            $this->migracion(self::INTENTOS)->down();
            $this->fail('No debe eliminarse el historial.');
        } catch (\RuntimeException) {
            $this->assertSame(1, DB::table('transacciones_pago')->count());
        }
    }

    public function test_sql_mysql_mariadb_se_compila_sin_conectar(): void
    {
        $original = Schema::getFacadeRoot();
        try {
            $args = [static fn () => throw new LogicException('Prohibido abrir PDO durante compilación.'),
                'solo_compilacion', '', ['driver' => 'mysql', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']];
            $conexiones = [
                new class(...$args) extends MySqlConnection {
                    public function isMaria() { return false; }
                    public function getServerVersion(): string { return '8.0.36'; }
                },
                new class(...$args) extends MariaDbConnection {
                    public function getServerVersion(): string { return '10.4.32'; }
                },
            ];
            foreach ($conexiones as $conexion) {
                Schema::swap($conexion->getSchemaBuilder());
                $sql = implode("\n", array_column($conexion->pretend(function () {
                    $this->migracion(self::COMPONENTES)->up();
                    $this->migracion(self::INTENTOS)->up();
                }), 'query'));
                $this->assertStringContainsString("CASE WHEN estado = 'PENDIENTE' THEN pedido_id ELSE NULL END", $sql);
                $this->assertStringContainsString('stored', $sql);
                $this->assertStringContainsString('transacciones_pago_pedido_activo_id_unique', $sql);
                $this->assertStringContainsString('transacciones_pago_venta_id_unique', $sql);
                $this->assertStringContainsString('on delete restrict', $sql);
                $this->assertStringContainsString('engine = InnoDB', $sql);
                $this->assertStringContainsString('`monto_tarjeta` decimal(8, 2)', $sql);
            }
        } finally { Schema::swap($original); }
    }
}
