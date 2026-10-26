<?php

declare(strict_types=1);

namespace Tests\Integration\CajaMariaDb;

use App\Http\Controllers\{PedidoController, VentaController};
use App\Models\{CierreCajaVersion, Pedido, Permiso, Rol, User, Venta};
use App\Services\{InventarioService, PedidoService};
use App\Services\Caja\{AutorizacionCaja, CierreDiarioService, CoordinacionCobrosCaja, CoordinadorCobrosCaja};
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Date};
use RuntimeException;

final class Runtime
{
    public static function cerrarFixture(string $fecha): \App\Models\CierreCajaVersion
    {
        Guard::exigirPreparada();
        $coordinacion = new class implements CoordinacionCobrosCaja {
            public function exclusivo(Closure $operacion): mixed
            {
                return (new CoordinadorCobrosCaja)->exclusivo($operacion);
            }

            public function permiteCierreDefinitivo(): bool
            {
                Guard::exigirPreparada();
                return true; // Exclusivamente base desechable marcada de integración.
            }
        };

        return (new CierreDiarioService($coordinacion))->confirmar($fecha, '12.35');
    }

    /** Asignación explícita SOLO al actor sintético id=1/rol=1 de esta base marcada. */
    public static function prepararAutorizacionActor(): void
    {
        Guard::exigirPreparada();
        DB::transaction(static function () {
            $rol = Rol::whereKey(1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey(1)->where('nombreUsuario', 'actor-integracion-caja')->lockForUpdate()->firstOrFail();
            self::comprobar((int) $actor->rol_id === 1, 'STOP: el actor sintético no tiene el rol esperado.');
            $ids = [];
            foreach ([AutorizacionCaja::CONSULTAR, AutorizacionCaja::CONFIRMAR, AutorizacionCaja::RECTIFICAR, AutorizacionCaja::COBRAR] as $nombre) {
                $permiso = Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => 'Permiso explícito del fixture de integración']);
                $ids[$permiso->id] = [];
            }
            $rol->permisos()->syncWithoutDetaching($ids);
        }, 1);
    }

    public static function comprobar(bool $condicion, string $mensaje): void
    {
        if (!$condicion) {
            throw new RuntimeException($mensaje);
        }
    }

    public static function reloj(string $hora): void
    {
        Date::setTestNow(CarbonImmutable::parse($hora, 'America/La_Paz'));
        \Illuminate\Support\Carbon::setTestNow(CarbonImmutable::parse($hora, 'America/La_Paz'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($hora, 'America/La_Paz'));
    }

    public static function actor(): User
    {
        $actor = User::where('nombreUsuario', 'actor-integracion-caja')->firstOrFail();
        Auth::setUser($actor); // Contexto de actor sintético; no inicia sesiones de usuarios reales.

        return $actor;
    }

    private static function request(array $datos): Request
    {
        $r = Request::create('/integracion-interna-sin-ruta', 'POST', $datos);
        $r->setUserResolver(static fn () => Auth::user());
        app()->instance('request', $r);

        return $r;
    }

    public static function pedido(int $producto): int
    {
        (new PedidoController)->store(self::request(['detalles' => [['producto_id' => $producto, 'cantidad' => 1]]]), new PedidoService);

        return (int) Pedido::whereHas('detalles', fn ($q) => $q->where('producto_id', $producto))->max('id');
    }

    public static function cobrar(int $pedido): void
    {
        $p = Pedido::findOrFail($pedido);
        (new VentaController)->store(self::request([
            'metodo_pago' => 'Efectivo', 'monto_recibido' => '20.00', 'version_pedido' => $p->versionCobro(),
        ]), $pedido, new InventarioService);
    }

    public static function senal(string $dir, string $nombre, array $datos = []): void
    {
        $ruta = $dir.'/'.$nombre.'.json';
        if (is_file($ruta)) {
            return; // Barreras idempotentes; no sobrescribir evidencias ya publicadas.
        }
        $tmp = $ruta.'.tmp';
        file_put_contents($tmp, json_encode($datos, JSON_THROW_ON_ERROR));
        if (!rename($tmp, $ruta)) {
            throw new RuntimeException('No se pudo publicar la barrera de sincronización.');
        }
    }

    public static function esperar(string $dir, string $nombre, int $segundos = 15): array
    {
        $limite = microtime(true) + $segundos;
        do {
            if (is_file($dir.'/'.$nombre.'.json')) {
                return json_decode(file_get_contents($dir.'/'.$nombre.'.json'), true, flags: JSON_THROW_ON_ERROR);
            }
            usleep(20_000);
        } while (microtime(true) < $limite);
        throw new RuntimeException('Timeout de barrera: '.$nombre);
    }

    public static function instalarCoordinacion(array $tarea, string $dir): void
    {
        $decorador = new class($tarea, $dir) implements CoordinacionCobrosCaja {
            public function __construct(private array $tarea, private string $dir) {}
            public function exclusivo(Closure $operacion): mixed
            {
                Guard::exigirPreparada();
                Runtime::senal($this->dir, $this->tarea['worker'].'-attempt');
                return (new CoordinadorCobrosCaja($this->tarea['timeout'] ?? 20))->exclusivo(function () use ($operacion) {
                    Runtime::senal($this->dir, $this->tarea['worker'].'-entered');
                    if (isset($this->tarea['hora_tras_bloqueo'])) {
                        Runtime::reloj($this->tarea['hora_tras_bloqueo']);
                    }
                    return $operacion();
                });
            }
            public function permiteCierreDefinitivo(): bool
            {
                // Excepción SOLO del arnés: exige la base exacta y su marca. No modifica la clase de producción.
                Guard::exigirPreparada();
                return true;
            }
        };
        app()->instance(CoordinacionCobrosCaja::class, $decorador);
        if (($tarea['hold'] ?? '') === 'sale') {
            Venta::creating(static function () use ($dir, $tarea) {
                Runtime::senal($dir, $tarea['worker'].'-held');
                Runtime::esperar($dir, $tarea['worker'].'-release');
            });
        }
        if (($tarea['hold'] ?? '') === 'close') {
            CierreCajaVersion::creating(static function () use ($dir, $tarea) {
                Runtime::senal($dir, $tarea['worker'].'-held');
                Runtime::esperar($dir, $tarea['worker'].'-release');
            });
        }
        if ($tarea['fallar_venta'] ?? false) {
            Venta::created(static fn () => throw new RuntimeException('FALLO_ROLLBACK_INTEGRACION'));
        }
    }
}
