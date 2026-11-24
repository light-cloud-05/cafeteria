<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use App\Services\Caja\CierreDiarioService;
use Illuminate\Validation\ValidationException;
use Tests\Integration\CajaMariaDb\{Guard, Runtime};

require __DIR__.'/Guard.php';
$dir = $argv[1] ?? '';
$worker = $argv[2] ?? '';
try {
    if (!in_array($worker, ['A', 'B'], true) || !is_dir($dir)
        || !str_starts_with(basename($dir), 'karma-caja-it-')) {
        throw new RuntimeException('Invocación de worker inválida.');
    }
    $tarea = json_decode(file_get_contents($dir.'/'.$worker.'-task.json'), true, flags: JSON_THROW_ON_ERROR);
    $esperada = Guard::validarVersionEsperada((string) ($tarea['version_esperada'] ?? ''));
    if ($esperada !== Guard::versionEsperada()) {
        throw new RuntimeException('Versión esperada del worker diferente del entorno heredado.');
    }
    [$app, $conexion, $meta] = Guard::boot();
    require __DIR__.'/Runtime.php';
    Guard::exigirPreparada();
    if ($tarea['worker'] !== $worker) {
        throw new RuntimeException('Identidad de worker inválida.');
    }
    if (!hash_equals(Guard::huella(), (string) ($tarea['codigo'] ?? ''))) {
        throw new RuntimeException('El código cambió durante la ejecución; detener antes de operar.');
    }
    Runtime::actor();
    Runtime::reloj($tarea['hora']);
    Runtime::instalarCoordinacion($tarea, $dir);
    Runtime::senal($dir, $worker.'-ready', ['sesion' => (int) $meta['sesion'],
        'version_esperada' => $meta['version_esperada'], 'version_real' => $meta['version_servidor']]);
    if ($tarea['accion'] === 'sale') {
        Runtime::cobrar((int) $tarea['pedido']);
    } elseif ($tarea['accion'] === 'close') {
        (new CierreDiarioService)->confirmar($tarea['fecha'], $tarea['contado']);
    } elseif ($tarea['accion'] === 'rectify') {
        (new \App\Services\Caja\RectificacionCierreDiarioService)->rectificar(
            (int) $tarea['cierre'], 1, $tarea['contado'], 'Recuento sintético de integración', 'Recuento verificado');
    } else {
        throw new RuntimeException('Acción de worker inválida.');
    }
    $resultado = ['estado' => 'ok', 'sesion' => (int) $meta['sesion']];
} catch (Throwable $e) {
    $resultado = ['estado' => 'error', 'tipo' => get_class($e)];
    if ($e instanceof ValidationException) {
        $resultado['estado'] = 'validacion';
        $resultado['campos'] = array_keys($e->errors());
    } elseif ($e->getMessage() === 'FALLO_ROLLBACK_INTEGRACION') {
        $resultado['estado'] = 'rollback_esperado';
    } else {
        // No imprimir mensajes PDO, credenciales, SQL, usuarios ni trazas con argumentos.
        $resultado['archivo'] = basename($e->getFile());
        $resultado['linea'] = $e->getLine();
    }
}
if (class_exists(Runtime::class) && is_dir($dir)) {
    Runtime::senal($dir, $worker.'-result', $resultado);
} else {
    if (is_dir($dir) && str_starts_with(basename($dir), 'karma-caja-it-') && in_array($worker, ['A', 'B'], true)) {
        file_put_contents($dir.'/'.$worker.'-result.json', json_encode($resultado, JSON_THROW_ON_ERROR));
    }
    fwrite(STDERR, "STOP: worker no pudo verificar el entorno de integración.\n");
}
exit(in_array($resultado['estado'], ['ok', 'validacion', 'rollback_esperado'], true) ? 0 : 1);
