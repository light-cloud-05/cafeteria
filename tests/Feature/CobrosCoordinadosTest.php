<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{CierreCaja, Pedido, Rol, Venta, User};
use App\Http\Controllers\VentaController;
use App\Services\InventarioService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use App\Http\Controllers\RolController;
use App\Services\Caja\{CierreDiarioService, CoordinacionCobrosCaja, CoordinadorCobrosCaja};
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class CobrosCoordinadosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new LogicException('CobrosCoordinadosTest solo admite SQLite en memoria.');
        }
        foreach (DB::select('PRAGMA database_list') as $base) {
            $this->assertSame('', $base->file);
        }
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            (require $archivo)->up();
        }
        DB::table('roles')->insert(['rol_id' => 1, 'nombre' => 'Prueba', 'estado' => 1]);
        DB::table('usuarios')->insert([
            'id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Actor prueba', 'nombreUsuario' => 'caja-prueba',
            'password' => 'no-es-una-credencial', 'estado' => 1,
        ]);
        foreach (['registrar_pedidos', 'cobrar_ventas', 'consultar_caja', 'confirmar_cierre_caja'] as $nombre) {
            $permiso = DB::table('permisos')->insertGetId(['nombre' => $nombre]);
            DB::table('rol_permisos')->insert(['idRol' => 1, 'idPermiso' => $permiso]);
        }
        DB::table('categorias')->insert(['id' => 1, 'nombre' => 'Bebidas', 'estado' => 1]);
        DB::table('productos')->insert(['id' => 1, 'nombre' => 'Café', 'precio' => '12.35', 'disponible' => 1, 'categoria_id' => 1]);
        DB::table('stocks')->insert(['producto_id' => 1, 'stock_actual' => 10, 'stock_minimo' => 2]);
        $this->actingAs(User::findOrFail(1));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-06 18:00:00', 'America/La_Paz'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function pedido(): Pedido
    {
        $this->post('/admin/pedidos/nuevo', ['detalles' => [['producto_id' => 1, 'cantidad' => 1]]])
            ->assertSessionHasNoErrors();

        return Pedido::latest('id')->firstOrFail();
    }

    private function cobrar(Pedido $pedido)
    {
        return $this->post('/admin/ventas/cobrar/'.$pedido->id, [
            'metodo_pago' => 'Efectivo', 'monto_recibido' => '20.00', 'version_pedido' => $pedido->fresh()->versionCobro(),
        ]);
    }

    /** Adaptador secuencial en memoria: usa el coordinador real pero NO certifica MariaDB. */
    private function coordinacionDePrueba(?Closure $antes = null): CoordinacionCobrosCaja
    {
        return new class($antes) implements CoordinacionCobrosCaja {
            public function __construct(private ?Closure $antes) {}
            public function exclusivo(Closure $operacion): mixed
            {
                if (!app()->runningUnitTests() || DB::connection()->getDatabaseName() !== ':memory:') {
                    throw new LogicException('El adaptador solo puede usarse en pruebas en memoria.');
                }
                return (new CoordinadorCobrosCaja)->exclusivo(function () use ($operacion) {
                    ($this->antes ?? static fn () => null)();
                    return $operacion();
                });
            }
            public function permiteCierreDefinitivo(): bool { return true; }
        };
    }

    public function test_cobro_normal_conserva_formulas_reserva_y_no_se_duplica(): void
    {
        $pedido = $this->pedido();
        $movimientos = DB::table('movimiento_inventarios')->count();
        $this->assertSame('9', (string) DB::table('stocks')->value('stock_actual'));
        $this->cobrar($pedido)->assertSessionHasNoErrors();
        $venta = Venta::firstOrFail();
        $this->assertSame('12.35', (string) $venta->total);
        $this->assertSame('7.65', (string) $venta->cambio);
        $this->assertSame('12.35', (string) $venta->monto_efectivo);
        $this->assertSame('Pagado', $pedido->fresh()->estado);
        $this->assertSame($movimientos, DB::table('movimiento_inventarios')->count());
        $this->assertSame('9', (string) DB::table('stocks')->value('stock_actual'));
        $this->cobrar($pedido)->assertSessionHasErrors('pedido');
        $this->assertSame(1, Venta::count());
        $this->assertSame($movimientos, DB::table('movimiento_inventarios')->count());
    }

    public function test_fecha_cerrada_rechaza_cobro_antes_de_tocar_pedido_o_inventario(): void
    {
        $pedido = $this->pedido();
        CierreCaja::create(['fecha_operativa' => '2026-10-06', 'fechaInicio' => '2026-10-06 04:00:00', 'idUsuario' => 1]);
        $movimientos = DB::table('movimiento_inventarios')->count();
        $this->cobrar($pedido)->assertSessionHasErrors('caja');
        $this->assertSame(0, Venta::count());
        $this->assertSame('Registrado', $pedido->fresh()->estado);
        $this->assertSame('9', (string) DB::table('stocks')->value('stock_actual'));
        $this->assertSame($movimientos, DB::table('movimiento_inventarios')->count());
    }

    public function test_espera_que_cruza_medianoche_asigna_fecha_despues_de_adquirir(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-06 23:59:59', 'America/La_Paz'));
        $pedido = $this->pedido();
        CierreCaja::create(['fecha_operativa' => '2026-10-06', 'fechaInicio' => '2026-10-06 04:00:00', 'idUsuario' => 1]);
        $this->app->instance(CoordinacionCobrosCaja::class, $this->coordinacionDePrueba(function () {
            $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 00:00:01', 'America/La_Paz'));
        }));
        $this->cobrar($pedido)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-07 04:00:01', DB::table('ventas')->value('fecha_hora'));
        $servicio = new CierreDiarioService;
        $this->assertSame(0, $servicio->previsualizar('2026-10-06')['cantidad_ventas']);
        $this->assertSame(1, $servicio->previsualizar('2026-10-07')['cantidad_ventas']);
    }

    public function test_cobro_iniciado_antes_de_medianoche_se_incluye_tras_terminar(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-06 23:59:59', 'America/La_Paz'));
        $pedido = $this->pedido();
        Venta::creating(function () {
            $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 00:00:01', 'America/La_Paz'));
        });
        $this->cobrar($pedido)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-07 03:59:59', DB::table('ventas')->value('fecha_hora'));
        $version = (new CierreDiarioService($this->coordinacionDePrueba()))->confirmar('2026-10-06', '12.35');
        $this->assertSame('12.35', $version->total_ventas);
        $this->assertCount(1, $version->detalles);
    }

    public function test_intercalado_simulado_no_permite_cierre_dentro_de_un_cobro(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-06 23:59:59', 'America/La_Paz'));
        $pedido = $this->pedido();
        $rechazado = false;
        Venta::creating(function () use (&$rechazado) {
            $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 00:00:01', 'America/La_Paz'));
            try {
                (new CierreDiarioService($this->coordinacionDePrueba()))->confirmar('2026-10-06', '0');
                $this->fail('El cierre no debe entrar antes de terminar el cobro.');
            } catch (LogicException) {
                $rechazado = true;
            }
        });
        $this->cobrar($pedido)->assertSessionHasNoErrors();
        $this->assertTrue($rechazado);
        $this->assertSame(0, CierreCaja::oficiales()->count());
        $version = (new CierreDiarioService($this->coordinacionDePrueba()))->confirmar('2026-10-06', '12.35');
        $this->assertCount(1, $version->detalles);
    }

    public function test_excepcion_del_cobro_revierte_y_permite_reintento_explicito(): void
    {
        $pedido = $this->pedido();
        $fallar = true;
        Venta::created(function () use (&$fallar) {
            if ($fallar) {
                throw new RuntimeException('Fallo simulado de cobro');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->cobrar($pedido);
            $this->fail('Debe propagar el fallo del cobro.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo simulado de cobro', $e->getMessage());
        }
        $this->assertSame(0, Venta::count());
        $this->assertSame('Registrado', $pedido->fresh()->estado);
        $this->assertSame('9', (string) DB::table('stocks')->value('stock_actual'));
        $this->assertSame(0, DB::transactionLevel());
        $fallar = false;
        $this->cobrar($pedido)->assertSessionHasNoErrors();
        $this->assertSame(1, Venta::count());
        $this->assertSame('9', (string) DB::table('stocks')->value('stock_actual'));
    }

    public function test_timeout_simulado_no_crea_venta_ni_altera_reserva(): void
    {
        $pedido = $this->pedido();
        $this->app->instance(CoordinacionCobrosCaja::class, $this->coordinacionDePrueba(function () {
            throw ValidationException::withMessages(['caja' => 'Timeout simulado']);
        }));
        $this->cobrar($pedido)->assertSessionHasErrors('caja');
        $this->assertSame(0, Venta::count());
        $this->assertSame('Registrado', $pedido->fresh()->estado);
        $this->assertSame('9', (string) DB::table('stocks')->value('stock_actual'));
    }

    private function finanzas(): array
    {
        $resultado=[];
        foreach (['pedidos','detalle_pedidos','ventas','stocks','movimiento_inventarios','bitacoras','cierre_cajas','cierre_caja_versiones','cierre_caja_version_ventas'] as $tabla) {
            $resultado[$tabla]=DB::table($tabla)->orderBy('id')->get()->toJson();
        }
        return $resultado;
    }

    public function test_revocacion_controlada_durante_espera_por_controlador_de_roles_no_permite_cobrar(): void
    {
        $pedido=$this->pedido();
        DB::table('roles')->insert(['rol_id'=>2,'nombre'=>'Supervisor sintético','estado'=>1]);
        DB::table('usuarios')->insert(['id'=>2,'rol_id'=>2,'nombreCompleto'=>'Supervisor sintético','nombreUsuario'=>'supervisor-cobro','password'=>'fixture','estado'=>1]);
        foreach (['gestion_roles','gestion_usuarios'] as $nombre) {
            $permiso=DB::table('permisos')->insertGetId(['nombre'=>$nombre]);
            DB::table('rol_permisos')->insert(['idRol'=>2,'idPermiso'=>$permiso]);
        }
        $antes=$this->finanzas(); $revocada=false;
        $this->app->instance(CoordinacionCobrosCaja::class,$this->coordinacionDePrueba(function () use (&$revocada) {
            // Otra operación ya terminó mientras el cobro esperaba: usar la sincronización real del controlador.
            $request=Request::create('/admin/roles/editar/1','POST',['estado'=>1,'permisos'=>DB::table('permisos')->where('nombre','<>','cobrar_ventas')->whereNotIn('nombre',['gestion_roles','gestion_usuarios'])->pluck('id')->all()]);
            $request->setUserResolver(fn()=>User::findOrFail(2));
            (new RolController)->update($request,Rol::findOrFail(1));
            $revocada=true;
        }));
        $this->cobrar($pedido)->assertForbidden();
        $this->assertTrue($revocada);
        $this->assertSame($antes,$this->finanzas());
        $this->assertSame(0,Venta::count());
    }

    public function test_cobrar_solo_exige_cobrar_ventas_y_no_permisos_de_caja(): void
    {
        $pedido=$this->pedido();
        $permiso=DB::table('permisos')->where('nombre','cobrar_ventas')->value('id');
        DB::table('rol_permisos')->where('idPermiso','<>',$permiso)->delete();
        $this->cobrar($pedido)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1,Venta::count());
        $this->assertSame(1,Venta::firstOrFail()->usuario_id);
        $this->assertSame('12.35',(string)Venta::firstOrFail()->monto_efectivo);
        $this->get('/admin/caja')->assertForbidden();
    }

    public function test_sin_permiso_el_controlador_directo_tampoco_adquiere_mutex_ni_escribe(): void
    {
        $pedido=$this->pedido(); $antes=$this->finanzas(); $adquirido=false;
        DB::table('rol_permisos')->where('idPermiso',DB::table('permisos')->where('nombre','cobrar_ventas')->value('id'))->delete();
        $this->app->instance(CoordinacionCobrosCaja::class,$this->coordinacionDePrueba(function () use (&$adquirido) { $adquirido=true; }));
        $this->cobrar($pedido)->assertForbidden();
        $request=Request::create('/interno-sin-middleware','POST',[
            'metodo_pago'=>'Efectivo','monto_recibido'=>'20.00','version_pedido'=>$pedido->versionCobro()]);
        $request->setUserResolver(fn()=>auth()->user());
        try { (new VentaController)->store($request,$pedido->id,new InventarioService); $this->fail('No depender solo del middleware.'); }
        catch (AuthorizationException) { $this->assertFalse($adquirido); }
        $this->assertSame($antes,$this->finanzas());
    }

    public function test_usuario_rol_desactivados_o_usuario_eliminado_durante_espera_no_generan_ingresos(): void
    {
        $pedido=$this->pedido();
        $this->withoutExceptionHandling();
        foreach (['usuario','rol','eliminado'] as $tipo) {
            DB::table('usuarios')->where('id',1)->update(['estado'=>1,'deleted_at'=>null]);
            DB::table('roles')->where('rol_id',1)->update(['estado'=>1]);
            $this->actingAs(User::findOrFail(1));
            $antes=$this->finanzas();
            $this->app->instance(CoordinacionCobrosCaja::class,$this->coordinacionDePrueba(function () use ($tipo) {
                match ($tipo) {
                    'usuario'=>DB::table('usuarios')->where('id',1)->update(['estado'=>0]),
                    'rol'=>DB::table('roles')->where('rol_id',1)->update(['estado'=>0]),
                    default=>DB::table('usuarios')->where('id',1)->update(['deleted_at'=>now()]),
                };
            }));
            try { $this->cobrar($pedido); $this->fail('No cobrar con usuario o rol inactivo.'); }
            catch (AuthenticationException) { $this->assertSame(0,DB::transactionLevel()); }
            $this->assertSame($antes,$this->finanzas());
        }
    }

    public function test_cambio_a_otro_rol_con_permiso_durante_espera_se_rechaza_sin_bloquear_el_rol_nuevo(): void
    {
        $pedido=$this->pedido();
        DB::table('roles')->insert(['rol_id'=>2,'nombre'=>'Segundo rol sintético','estado'=>1]);
        DB::table('rol_permisos')->insert(['idRol'=>2,'idPermiso'=>DB::table('permisos')->where('nombre','cobrar_ventas')->value('id')]);
        $antes=$this->finanzas();
        $this->app->instance(CoordinacionCobrosCaja::class,$this->coordinacionDePrueba(fn()=>DB::table('usuarios')->where('id',1)->update(['rol_id'=>2])));
        $this->cobrar($pedido)->assertForbidden();
        $this->assertSame($antes,$this->finanzas());
    }

    public function test_cambio_de_actor_durante_espera_no_sustituye_responsable(): void
    {
        $pedido=$this->pedido();
        DB::table('usuarios')->insert(['id'=>2,'rol_id'=>1,'nombreCompleto'=>'Segundo actor sintético','nombreUsuario'=>'segundo-cobro','password'=>'fixture','estado'=>1]);
        $antes=$this->finanzas();
        $this->app->instance(CoordinacionCobrosCaja::class,$this->coordinacionDePrueba(fn()=>\Illuminate\Support\Facades\Auth::setUser(User::findOrFail(2))));
        $this->cobrar($pedido)->assertForbidden();
        $this->assertSame($antes,$this->finanzas());
    }

    public function test_orden_de_revalidacion_transaccional_es_rol_usuario_permisos_antes_de_pedido(): void
    {
        $pedido=$this->pedido(); $orden=[];
        DB::listen(function ($evento) use (&$orden) {
            if (DB::transactionLevel() !== 1) return;
            foreach (['roles','usuarios','permisos','pedidos'] as $tabla) {
                if (str_contains($evento->sql,'from "'.$tabla.'"')) $orden[]=$tabla;
            }
        });
        $this->cobrar($pedido)->assertSessionHasNoErrors();
        $this->assertSame('roles',$orden[0]);
        $this->assertSame('usuarios',$orden[1]);
        $this->assertLessThan(array_search('permisos',$orden,true),array_search('usuarios',$orden,true));
        $this->assertLessThan(array_search('pedidos',$orden,true),array_search('permisos',$orden,true));
        $this->assertSame(1,Venta::count());
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
    }
}
