<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use App\Services\Caja\{CoordinadorCobrosCaja, ImporteCaja};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Database\Schema\Blueprint;
use Tests\Integration\CajaMariaDb\{Catalogo, Consolidados, Guard, Runtime};

require __DIR__.'/Guard.php';
require __DIR__.'/Catalogo.php';
const CASOS_CAJA = Catalogo::CONCURRENCIA; // Compatibilidad: --case=all sigue siendo 11 casos por defecto.

function ayudaCaja(): void
{
    echo "KARMA_CAJA_EXPECTED_VERSION: 10.4.32 o 10.11.14; ausente exige 10.4.32.\n";
    echo "Uso (no conecta con --help):\n  php run.php --prepare\n  php run.php --suite=concurrencia --case=all --repeat=3\n  php run.php --suite=consolidados --case=all --repeat=3\n  php run.php --suite=ampliada --case=all --repeat=3\n  Sin --suite se conserva concurrencia (11 casos).\n  Concurrencia: ".implode('|', CASOS_CAJA)."\n  Consolidados: ".implode('|', Catalogo::CONSOLIDADOS)."\n";
}

function huellaCaja(): string
{
    return Guard::huella();
}

function prepararCaja(): void
{
    $cantidad = DB::selectOne('SELECT COUNT(*) AS cantidad FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [Guard::BASE])->cantidad;
    Runtime::comprobar((int) $cantidad === 0, 'STOP: la base no está vacía. No se borrará ni reiniciará.');
    foreach (glob(dirname(__DIR__, 3).'/database/migrations/*.php') as $archivo) {
        (require $archivo)->up(); // Solo up(), sin seeders ni migrate:fresh, en base recién creada y verificada.
    }
    Schema::create('caja_it_control', static function (Blueprint $t) {
        $t->unsignedInteger('id')->primary();
        $t->string('marca');
    });
    Schema::create('caja_it_runs', static function (Blueprint $t) {
        $t->id();
        $t->string('caso');
    });
    DB::table('roles')->insert(['rol_id' => 1, 'nombre' => 'Integración caja', 'estado' => 1]);
    DB::table('usuarios')->insert(['id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Actor sintético integración',
        'nombreUsuario' => 'actor-integracion-caja', 'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT), 'estado' => 1]);
    DB::table('categorias')->insert(['id' => 1, 'nombre' => 'Integración caja', 'estado' => 1]);
    DB::table('caja_it_control')->insert(['id' => 1, 'marca' => Guard::MARCA]);
    Runtime::prepararAutorizacionActor();
    echo "PREPARADA: base desechable verificada; esquema y actor sintético creados. No se borró ninguna tabla.\n";
}

function iniciarWorkerCaja(string $dir, array $tarea): array
{
    $tarea['version_esperada'] = Guard::versionEsperada();
    Runtime::senal($dir, $tarea['worker'].'-task', $tarea);
    $proceso = proc_open([PHP_BINARY, __DIR__.'/worker.php', $dir, $tarea['worker']], [
        0 => ['pipe', 'r'], 1 => ['file', $dir.'/'.$tarea['worker'].'.stdout', 'a'],
        2 => ['file', $dir.'/'.$tarea['worker'].'.stderr', 'a'],
    ], $pipes, dirname(__DIR__, 3), null, ['bypass_shell' => true, 'create_new_console' => false]);
    Runtime::comprobar(is_resource($proceso), 'No se pudo iniciar un proceso PHP independiente.');
    fclose($pipes[0]);

    return [$proceso, $tarea['worker']];
}

function observarEsperaCaja(int $sesion, string $dir): void
{
    $limite = microtime(true) + 10;
    do {
        $fila = DB::selectOne('SELECT STATE FROM information_schema.PROCESSLIST WHERE ID = ?', [$sesion]);
        if ($fila && stripos((string) $fila->STATE, 'User lock') !== false) {
            Runtime::comprobar(!is_file($dir.'/B-entered.json'), 'B ejecutó el callback mientras A conservaba el bloqueo.');
            return;
        }
        if (is_file($dir.'/B-result.json')) {
            throw new RuntimeException('B terminó sin que se observara espera real User lock.');
        }
        usleep(20_000);
    } while (microtime(true) < $limite);
    throw new RuntimeException('No se pudo observar User lock; revisar PROCESSLIST y las barreras. No se considera aprobado.');
}

function finalizarWorkerCaja(array $worker): void
{
    [$proceso] = $worker;
    $limite = microtime(true) + 15;
    do {
        $estado = proc_get_status($proceso);
        if (!$estado['running']) {
            $codigo = $estado['exitcode'];
            $cerrado = proc_close($proceso);
            Runtime::comprobar(($codigo >= 0 ? $codigo : $cerrado) === 0, 'Worker terminó con error; revisar su resultado agregado.');
            return;
        }
        usleep(20_000);
    } while (microtime(true) < $limite);
    proc_terminate($proceso);
    proc_close($proceso);
    throw new RuntimeException('Worker no terminó; proceso detenido y caso no aprobado.');
}

function comprobarIntegridadCaja(array $pedidos, int $producto, int $movimientos, string $dia, int $ventasEsperadas, int $cierresEsperados, int $versionesEsperadas = 1, string $diferencia = '0.00'): array
{
    $ventas = DB::table('ventas')->whereIn('pedido_id', $pedidos)->get();
    Runtime::comprobar($ventas->count() === $ventasEsperadas, 'Cantidad inesperada de ventas confirmadas.');
    Runtime::comprobar($ventas->pluck('pedido_id')->unique()->count() === $ventas->count(), 'Hay ventas duplicadas por pedido.');
    Runtime::comprobar((int) DB::table('stocks')->where('producto_id', $producto)->value('stock_actual') === 10 - count($pedidos), 'La coordinación alteró las reservas de inventario.');
    Runtime::comprobar(DB::table('movimiento_inventarios')->where('producto_id', $producto)->count() === $movimientos, 'El cobro volvió a descontar existencias.');
    foreach ($pedidos as $pedido) {
        $pagado = $ventas->contains('pedido_id', $pedido);
        Runtime::comprobar(DB::table('pedidos')->where('id', $pedido)->value('estado') === ($pagado ? 'Pagado' : 'Registrado'), 'Pedido incoherente con el resultado del cobro.');
    }
    $cierre = DB::table('cierre_cajas')->where('fecha_operativa', $dia)->first();
    Runtime::comprobar(($cierre ? 1 : 0) === $cierresEsperados, 'Cantidad inesperada de cierres oficiales.');
    $incluidas = 0;
    if ($cierre) {
        $versiones = DB::table('cierre_caja_versiones')->where('cierre_caja_id', $cierre->id)->orderBy('numero_version')->get();
        Runtime::comprobar($versiones->count() === $versionesEsperadas, 'Cantidad inesperada de versiones históricas.');
        $version = $versiones->last();
        $inicio = CarbonImmutable::parse($dia, 'America/La_Paz')->startOfDay();
        $esperadas = DB::table('ventas')->where('estado', 'Completado')
            ->where('fecha_hora', '>=', $inicio->utc()->format('Y-m-d H:i:s'))
            ->where('fecha_hora', '<', $inicio->addDay()->utc()->format('Y-m-d H:i:s'))->orderBy('id')->get();
        $detalles = DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $version->id)->orderBy('venta_id')->get();
        Runtime::comprobar($esperadas->pluck('id')->map(fn ($id) => (int) $id)->all()
            === $detalles->pluck('venta_id')->map(fn ($id) => (int) $id)->all(), 'El cierre omitió o agregó ventas de otra fecha.');
        $total = $efectivo = $qr = 0;
        foreach ($esperadas as $i => $venta) {
            $d = $detalles[$i];
            foreach (['total', 'monto_efectivo', 'monto_qr'] as $campo) {
                Runtime::comprobar((string) $venta->{$campo} === (string) $d->{$campo}, 'La instantánea monetaria no coincide con la venta confirmada.');
            }
            Runtime::comprobar($d->fecha_cobro === $venta->fecha_hora && $d->metodo_pago === $venta->metodo_pago, 'La instantánea histórica es incorrecta.');
            $total = ImporteCaja::sumar($total, ImporteCaja::centavos($venta->total));
            $efectivo = ImporteCaja::sumar($efectivo, ImporteCaja::centavos($venta->monto_efectivo));
            $qr = ImporteCaja::sumar($qr, ImporteCaja::centavos($venta->monto_qr));
        }
        Runtime::comprobar((string) $version->total_ventas === ImporteCaja::decimal($total)
            && (string) $version->efectivo_esperado === ImporteCaja::decimal($efectivo)
            && (string) $version->qr_registrado === ImporteCaja::decimal($qr)
            && (string) $version->diferencia === $diferencia, 'El cierre no concilia con sus ventas.');
        $incluidas = $detalles->count();
    }
    $s = DB::getPdo()->prepare('SELECT IS_FREE_LOCK(?)');
    $s->execute([CoordinadorCobrosCaja::nombreBloqueo(Guard::BASE)]);
    Runtime::comprobar((int) $s->fetchColumn() === 1, 'Quedó retenido el bloqueo de operaciones.');

    return ['ventas' => $ventas->count(), 'cierres' => $cierresEsperados, 'instantaneas' => $incluidas, 'reservas_integras' => true];
}

function ejecutarCasoCaja(string $caso, string $raiz, string $huella, int $sesionPrincipal): array
{
    Guard::exigirPreparada();
    $run = DB::table('caja_it_runs')->insertGetId(['caso' => $caso]);
    $dia = CarbonImmutable::parse('2020-01-01', 'America/La_Paz')->addDays($run * 2)->toDateString();
    $siguiente = CarbonImmutable::parse($dia, 'America/La_Paz')->addDay()->toDateString();
    Runtime::reloj($dia.' 18:00:00');
    Runtime::actor();
    $rectificando = in_array($caso, ['rectificaciones-duplicadas', 'rectificacion-y-cobro'], true);
    $producto = DB::table('productos')->insertGetId(['nombre' => 'Producto sintético '.$run, 'precio' => '12.35', 'disponible' => 1, 'categoria_id' => 1]);
    DB::table('stocks')->insert(['producto_id' => $producto, 'stock_actual' => 10, 'stock_minimo' => 2]);
    $cantidadPedidos = in_array($caso, ['dos-cobros', 'timeout', 'rollback', 'cobro-durante-cierre', 'medianoche', 'fecha-cerrada', 'rectificacion-y-cobro'], true) ? 2 : 1;
    $pedidos = [];
    for ($i = 0; $i < $cantidadPedidos; $i++) {
        $pedidos[] = Runtime::pedido($producto);
    }
    if ($rectificando || in_array($caso, ['cierres-duplicados', 'cobro-durante-cierre', 'medianoche', 'fecha-cerrada'], true)) {
        Runtime::cobrar($pedidos[0]);
    }
    if ($rectificando) {
        Runtime::reloj($siguiente.' 00:00:01');
        $inicial = Runtime::cerrarFixture($dia);
        $historial = [
            DB::table('cierre_cajas')->where('id', $inicial->cierre_caja_id)->first(),
            DB::table('cierre_caja_versiones')->where('id', $inicial->id)->first(),
            DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $inicial->id)->orderBy('id')->get()->all(),
        ];
    }
    $movimientos = DB::table('movimiento_inventarios')->where('producto_id', $producto)->count();
    $a = ['worker' => 'A', 'codigo' => $huella, 'accion' => 'sale', 'pedido' => $pedidos[0],
        'fecha' => $dia, 'hora' => $dia.' 23:59:59', 'hold' => 'sale'];
    $b = ['worker' => 'B', 'codigo' => $huella, 'accion' => 'sale', 'pedido' => $pedidos[1] ?? $pedidos[0],
        'fecha' => $dia, 'hora' => $dia.' 23:59:59'];
    $esperadoA = 'ok';
    $esperadoB = 'ok';
    $campo = null;
    $ventasEsperadas = 2;
    $cierresEsperados = 0;
    if ($rectificando) {
        $a = array_replace($a, ['accion' => 'rectify', 'cierre' => $inicial->cierre_caja_id,
            'hora' => $siguiente.' 00:00:01', 'hold' => 'close', 'contado' => '11.35']);
        $cierresEsperados = 1;
        if ($caso === 'rectificaciones-duplicadas') {
            $b = array_replace($b, ['accion' => 'rectify', 'cierre' => $inicial->cierre_caja_id,
                'hora' => $siguiente.' 00:00:02', 'contado' => '10.35']);
            $esperadoB = 'validacion'; $campo = 'version_esperada'; $ventasEsperadas = 1;
        } else {
            $b['hora'] = $siguiente.' 00:00:02';
        }
    } elseif ($caso === 'cobro-y-cierre') {
        $b = array_replace($b, ['accion' => 'close', 'hora' => $siguiente.' 00:00:01', 'contado' => '12.35']);
        $ventasEsperadas = $cierresEsperados = 1;
    } elseif (in_array($caso, ['cierres-duplicados', 'cobro-durante-cierre', 'medianoche', 'fecha-cerrada'], true)) {
        $a = array_replace($a, ['accion' => 'close', 'hora' => $siguiente.' 00:00:01', 'hold' => 'close', 'contado' => '12.35']);
        $cierresEsperados = 1;
        if ($caso === 'cierres-duplicados') {
            $b = array_replace($b, ['accion' => 'close', 'hora' => $siguiente.' 00:00:02', 'contado' => '12.35']);
            $esperadoB = 'validacion'; $campo = 'fecha'; $ventasEsperadas = 1;
        } elseif ($caso === 'fecha-cerrada') {
            $esperadoB = 'validacion'; $campo = 'caja'; $ventasEsperadas = 1;
        } elseif ($caso === 'medianoche') {
            $b['hora_tras_bloqueo'] = $siguiente.' 00:00:02';
        } else {
            $b['hora'] = $siguiente.' 00:00:02';
        }
    } elseif ($caso === 'timeout') {
        $b['timeout'] = 2; $esperadoB = 'validacion'; $campo = 'caja'; $ventasEsperadas = 1;
    } elseif ($caso === 'rollback') {
        $a['fallar_venta'] = true; $esperadoA = 'rollback_esperado'; $ventasEsperadas = 1;
    } elseif ($caso === 'mismo-pedido') {
        $esperadoB = 'validacion'; $campo = 'pedido'; $ventasEsperadas = 1;
    }
    $dir = $raiz.'/karma-caja-it-'.$run;
    mkdir($dir, 0700);
    $procesos = [];
    try {
        $procesos[] = iniciarWorkerCaja($dir, $a);
        $metaA = Runtime::esperar($dir, 'A-ready');
        Runtime::esperar($dir, 'A-held');
        $procesos[] = iniciarWorkerCaja($dir, $b);
        $metaB = Runtime::esperar($dir, 'B-ready');
        foreach ([$metaA, $metaB] as $metaWorker) {
            Runtime::comprobar($metaWorker['version_esperada'] === Guard::versionEsperada(), 'Worker con versión esperada diferente.');
        }
        Runtime::comprobar(count(array_unique([$sesionPrincipal, $metaA['sesion'], $metaB['sesion']])) === 3, 'No hay tres sesiones independientes.');
        Runtime::esperar($dir, 'B-attempt');
        observarEsperaCaja((int) $metaB['sesion'], $dir);
        if ($caso === 'timeout') {
            $rB = Runtime::esperar($dir, 'B-result'); // A sigue reteniendo el lock: B debe fallar sin entrar.
            Runtime::comprobar(!is_file($dir.'/B-entered.json'), 'El timeout permitió continuar con el cobro.');
        }
        Runtime::senal($dir, 'A-release');
        $rA = Runtime::esperar($dir, 'A-result');
        $rB ??= Runtime::esperar($dir, 'B-result');
        Runtime::comprobar($rA['estado'] === $esperadoA && $rB['estado'] === $esperadoB,
            'Resultados de workers inesperados: revisar archivos agregados del caso.');
        if ($campo) {
            Runtime::comprobar(in_array($campo, $rB['campos'] ?? [], true), 'La validación no corresponde al fallo esperado.');
        }
    } finally {
        Runtime::senal($dir, 'A-release');
        $falloLimpieza = null;
        foreach ($procesos as $p) {
            try {
                finalizarWorkerCaja($p);
            } catch (Throwable $e) {
                $falloLimpieza ??= $e;
            }
        }
        if ($falloLimpieza) {
            throw $falloLimpieza;
        }
    }
    $resumen = comprobarIntegridadCaja($pedidos, $producto, $movimientos, $dia, $ventasEsperadas, $cierresEsperados,
        $rectificando ? 2 : 1, $rectificando ? '-1.00' : '0.00');
    if ($rectificando) {
        $actual = [DB::table('cierre_cajas')->where('id', $inicial->cierre_caja_id)->first(),
            DB::table('cierre_caja_versiones')->where('id', $inicial->id)->first(),
            DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $inicial->id)->orderBy('id')->get()->all()];
        Runtime::comprobar(serialize($actual) === serialize($historial), 'La rectificación alteró el historial anterior.');
        $vigente = DB::table('cierre_caja_versiones')->where('cierre_caja_id', $inicial->cierre_caja_id)->where('numero_version', 2)->first();
        Runtime::comprobar(DB::table('bitacoras')->where('accion', 'RECTIFICACION_CAJA')->where('idRegistroAfectado', $vigente->id)->count() === 1,
            'Auditoría de rectificación ausente o duplicada.');
        if ($caso === 'rectificaciones-duplicadas') {
            Runtime::comprobar(DB::table('bitacoras')->where('accion', 'RECTIFICACION_CAJA_RECHAZADA')->where('idRegistroAfectado', $inicial->cierre_caja_id)->count() === 1,
                'El intento obsoleto no dejó auditoría única.');
        }
    }
    if (in_array($caso, ['medianoche', 'cobro-durante-cierre'], true)) {
        $ventaB = DB::table('ventas')->where('pedido_id', $pedidos[1])->first();
        Runtime::comprobar(CarbonImmutable::parse($ventaB->fecha_hora, 'UTC')->setTimezone('America/La_Paz')->toDateString() === $siguiente,
            'El cobro que esperó medianoche fue asignado al día cerrado.');
    }

    return ['caso' => $caso, 'estado' => 'PASS', 'espera_real_observada' => true, 'sesiones_independientes' => 3,
        'workers' => [$metaA, $metaB]] + $resumen;
}

$opciones = getopt('', ['help', 'prepare', 'suite:', 'case:', 'repeat:']);
if (isset($opciones['help']) || (!$opciones && count($argv) === 1)) {
    ayudaCaja(); exit(0);
}
$reporte = ['ejecutado_utc' => gmdate('c'), 'casos' => [], 'habilita_cierre_produccion' => false];
$pdoRunner = null;
$runnerLock = false;
try {
    $preparar = array_key_exists('prepare', $opciones);
    $caso = $opciones['case'] ?? '';
    $suite = (string)($opciones['suite'] ?? 'concurrencia');
    $disponibles = Catalogo::casos($suite);
    $repetir = (string) ($opciones['repeat'] ?? '1');
    if (($preparar && ($caso !== '' || isset($opciones['suite']))) || (!$preparar && $caso !== 'all' && !in_array($caso, $disponibles, true))
        || !ctype_digit($repetir) || (int) $repetir < 1 || (int) $repetir > 20) {
        throw new RuntimeException('Argumentos inválidos; usar --help.');
    }
    $reporte['version_esperada'] = Guard::versionEsperada();
    [$app, $conexion, $meta] = Guard::boot();
    $reporte['version_real'] = $meta['version_servidor'];
    require __DIR__.'/Runtime.php';
    require __DIR__.'/Consolidados.php';
    $pdoRunner = $conexion->getPdo();
    $s = $pdoRunner->prepare('SELECT GET_LOCK(?, 0)');
    $s->execute(['karma:caja:integracion:runner']);
    Runtime::comprobar((int) $s->fetchColumn() === 1, 'Ya hay otra suite utilizando la base desechable.');
    $runnerLock = true;
    if ($preparar) {
        prepararCaja();
    } else {
        Guard::exigirPreparada();
        Runtime::prepararAutorizacionActor(); // Compatibilidad con fixtures anteriores; solo cuenta sintética.
        $raiz = sys_get_temp_dir().'/karma-caja-it-'.bin2hex(random_bytes(8));
        mkdir($raiz, 0700);
        $reporte['base'] = Guard::BASE;
        $reporte['suite'] = $suite;
        $reporte['servidor'] = $meta['version_servidor'];
        $reporte['php'] = PHP_VERSION;
        $reporte['codigo_sha256'] = huellaCaja();
        $reporte['reloj_aplicacion_controlado'] = true;
        $reporte['directorio'] = $raiz;
        for ($n = 1; $n <= (int) $repetir; $n++) {
            foreach ($caso === 'all' ? $disponibles : [$caso] as $nombre) {
                $reporte['caso_en_curso'] = $nombre;
                $reporte['repeticion_en_curso'] = $n;
                echo "EJECUTANDO {$nombre}, repetición {$n}\n";
                $consolidado = in_array($nombre, Catalogo::CONSOLIDADOS, true);
                $r = $consolidado ? Consolidados::ejecutar($nombre)
                    : ejecutarCasoCaja($nombre, $raiz, $reporte['codigo_sha256'], (int) $meta['sesion']);
                $reporte['casos'][] = $r + ['repeticion' => $n];
                echo $consolidado ? "PASS {$nombre}: DECIMAL, cobertura e integridad de lecturas verificadas.\n"
                    : "PASS {$nombre}: sesiones independientes, espera real e invariantes verificadas.\n";
            }
        }
        $reporte['estado'] = 'PASS';
    }
} catch (Throwable $e) {
    $reporte['estado'] = 'FAIL';
    $reporte['error'] = ['tipo' => get_class($e), 'archivo' => basename($e->getFile()), 'linea' => $e->getLine()];
    // Solo los mensajes del arnés son seguros; nunca imprimir mensajes de PDO/Laravel.
    if (get_class($e) === RuntimeException::class) {
        $reporte['error']['motivo'] = $e->getMessage();
    }
    echo "FAIL: se detuvo la suite. No se habilita el cierre.\n";
} finally {
    if ($runnerLock) {
        try {
            $s = $pdoRunner->prepare('SELECT RELEASE_LOCK(?)');
            $s->execute(['karma:caja:integracion:runner']);
            if ((int) $s->fetchColumn() !== 1) {
                throw new RuntimeException('Liberación del lock del runner no confirmada.');
            }
        } catch (Throwable) {
            $reporte['estado'] = 'FAIL';
            $reporte['error_liberacion_runner'] = true;
        }
    }
}
if (isset($raiz)) {
    file_put_contents($raiz.'/report.json', json_encode($reporte, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo 'REPORTE: '.$raiz."/report.json\n";
} elseif (($reporte['estado'] ?? '') === 'FAIL') {
    echo json_encode($reporte['error'] ?? ['error_liberacion_runner' => true], JSON_THROW_ON_ERROR)."\n";
}
exit(($reporte['estado'] ?? 'PASS') === 'PASS' ? 0 : 1);
