<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Caja\{AislamientoCajaDemo, CoordinacionCajaDemostracion, DiaOperativoCaja};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use RuntimeException;

final class PrepararCajaDemostracion extends Command
{
    protected $signature = 'caja:preparar-demo {--acepto-base= : Debe ser cafeteria_defensa_demo}';
    protected $description = 'Inserta fixtures académicos SOLO en la base demo vacía y previamente instalada; nunca migra ni borra.';

    public function handle(AislamientoCajaDemo $aislamiento): int
    {
        $this->warn('Destino exclusivo: cafeteria_defensa_demo, cuenta karma_defensa_demo, conexión TCP local verificada.');
        $this->line('Protecciones: entorno demostracion + activación expresa + identidad/grants efectivos + esquema vacío + transacción y mutex. No se borran ni actualizan registros existentes.');
        if ($this->option('acepto-base') !== AislamientoCajaDemo::BASE) {
            $this->error('Falta --acepto-base=cafeteria_defensa_demo. Sin escrituras.');
            return self::FAILURE;
        }
        $aislamiento->verificar();
        $password = Str::random(24);
        $fecha = DiaOperativoCaja::ahora()->subDay()->toDateString();
        (new CoordinacionCajaDemostracion($aislamiento))->exclusivo(function () use ($fecha, $password) {
            $tablas = DB::select('SELECT TABLE_NAME AS nombre, TABLE_TYPE AS tipo, ENGINE AS motor FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [AislamientoCajaDemo::BASE]);
            $nombres = array_map(static fn ($t) => $t->nombre, $tablas);
            foreach (['usuarios', 'roles', 'permisos', 'rol_permisos', 'categorias', 'productos', 'stocks',
                'pedidos', 'detalle_pedidos', 'ventas', 'bitacoras', 'numeradores_pedidos',
                'cierre_cajas', 'cierre_caja_versiones', 'cierre_caja_version_ventas'] as $tabla) {
                if (!in_array($tabla, $nombres, true)) throw new RuntimeException('Falta el esquema requerido. No se ejecutan migraciones.');
            }
            foreach ($tablas as $tabla) {
                if ($tabla->tipo !== 'BASE TABLE' || $tabla->motor !== 'InnoDB'
                    || !preg_match('/\A[a-z_]+\z/', $tabla->nombre)) throw new RuntimeException('Esquema demo no admitido.');
                if ($tabla->nombre !== 'migrations' && DB::table($tabla->nombre)->exists()) {
                    throw new RuntimeException('El preparador exige todas las tablas vacías, excepto migrations. No se modifica ningún dato existente.');
                }
            }
            if (DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', AislamientoCajaDemo::BASE)->exists()) {
                throw new RuntimeException('No se admiten triggers en la base demo.');
            }
            DB::transaction(function () use ($fecha, $password) {
                $rol = DB::table('roles')->insertGetId(['nombre' => 'Responsable académico ficticio', 'estado' => 1], 'rol_id');
                $usuario = DB::table('usuarios')->insertGetId(['rol_id' => $rol, 'nombreCompleto' => 'Persona ficticia de defensa',
                    'nombreUsuario' => 'defensa.demo', 'email' => 'defensa@example.invalid', 'password' => Hash::make($password), 'estado' => 1]);
                foreach (['consultar_caja', 'confirmar_cierre_caja', 'rectificar_cierre_caja', 'consultar_bitacora',
                    'cobrar_ventas', 'registrar_pedidos', 'modificar_pedidos', 'consultar_historial_pedidos',
                    'buscar_productos', 'consultar_stock', 'ver_reportes'] as $nombre) {
                    $permiso = DB::table('permisos')->insertGetId(['nombre' => $nombre]);
                    DB::table('rol_permisos')->insert(['idRol' => $rol, 'idPermiso' => $permiso]);
                }
                $categoria = DB::table('categorias')->insertGetId(['nombre' => 'Categoría ficticia', 'estado' => 1]);
                $producto = DB::table('productos')->insertGetId(['nombre' => 'Producto ficticio Bs 10', 'precio' => '10.00', 'disponible' => 1, 'categoria_id' => $categoria]);
                DB::table('stocks')->insert(['producto_id' => $producto, 'stock_actual' => 100, 'stock_minimo' => 5]);
                $hora = \Carbon\CarbonImmutable::parse($fecha.' 12:00:00', 'America/La_Paz')->utc()->format('Y-m-d H:i:s');
                foreach ([['30.00', '30.00', '0.00', 'Efectivo', 3], ['20.00', '0.00', '20.00', 'QR', 2], ['50.00', '20.00', '30.00', 'Mixto', 5]] as $i => [$total, $efectivo, $qr, $metodo, $cantidad]) {
                    $pedido = DB::table('pedidos')->insertGetId(['usuario_id' => $usuario, 'fechaHora' => $hora,
                        'fecha_numero' => $fecha, 'numero_diario' => $i + 1, 'estado' => 'Pagado', 'total' => $total,
                        'subtotal' => $total, 'descuento' => '0.00', 'stock_reservado' => 0]);
                    DB::table('detalle_pedidos')->insert(['pedido_id' => $pedido, 'producto_id' => $producto,
                        'precioUnitario' => '10.00', 'cantidad' => $cantidad, 'subtotal' => $total, 'opcion' => 'Fixture académico']);
                    DB::table('ventas')->insert(['pedido_id' => $pedido, 'usuario_id' => $usuario, 'fecha_hora' => $hora,
                        'total' => $total, 'monto_efectivo' => $efectivo, 'monto_qr' => $qr, 'metodo_pago' => $metodo,
                        'monto_recibido' => $total, 'cambio' => '0.00', 'estado' => 'Completado']);
                }
                DB::table('numeradores_pedidos')->insert(['fecha' => $fecha, 'ultimo' => 3]);
                DB::table('bitacoras')->insert(['tablaAfectada' => 'usuarios', 'idRegistroAfectado' => $usuario, 'accion' => 'PREPARACION_DEMO',
                    'fechaHora' => now()->utc()->format('Y-m-d H:i:s'), 'idUsuario' => $usuario,
                    'valoresNuevos' => json_encode(['origen' => 'Fixtures académicos; no cobros reales', 'fecha' => $fecha], JSON_THROW_ON_ERROR)]);
            }, 1);
        });
        $this->info('DEMOSTRACIÓN ACADÉMICA — DATOS FICTICIOS');
        $this->line('Usuario nuevo: defensa.demo. Contraseña nueva (guardar fuera del repositorio): '.$password);
        $this->line("Fecha a cerrar: {$fecha}. Total Bs 100.00; efectivo Bs 50.00; QR Bs 50.00. Sin cierres precargados.");
        return self::SUCCESS;
    }
}
