<?php

declare(strict_types=1);

namespace Tests\Integration\CajaMariaDb;

use App\Services\Caja\{ConsolidacionCajaService, CoordinadorCobrosCaja, TotalesCajaPeriodo};
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Fixtures sintéticos append-only; no ejecuta nada al cargar el archivo. */
final class Consolidados
{
    private bool $vigilando = false;
    private int $lecturasFinancieras = 0;
    private const TABLAS = ['ventas', 'pedidos', 'detalle_pedidos', 'cierre_cajas',
        'cierre_caja_versiones', 'cierre_caja_version_ventas', 'bitacoras', 'stocks',
        'movimiento_inventarios', 'productos', 'usuarios', 'rol_permisos'];

    public function __construct()
    {
        Guard::autorizarFixtures();
        DB::connection()->beforeExecuting(function (string $sql) {
            if (!$this->vigilando) return;
            if (!preg_match('/\A\s*SELECT\b/i', $sql) || stripos($sql, 'GET_LOCK') !== false
                || stripos($sql, 'RELEASE_LOCK') !== false || stripos($sql, 'FOR UPDATE') !== false) {
                throw new RuntimeException('STOP: un consolidado intentó escribir o bloquear.');
            }
            if (preg_match('/\bfrom\s+[`"]?cierre_cajas[`"]?\s+as\s+[`"]?c[`"]?/i', $sql)) $this->lecturasFinancieras++;
        });
    }

    public static function ejecutar(string $caso): array
    {
        if (!in_array($caso, Catalogo::CONSOLIDADOS, true)) throw new RuntimeException('Caso de consolidación inválido.');
        $prueba = new self;
        if ($caso === 'conso-decimales' && DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('STOP: conso-decimales requiere DECIMAL real de MariaDB; SQLite no lo certifica.');
        }
        $tiposVerificados = DB::connection()->getDriverName() === 'mysql';
        if ($tiposVerificados) self::verificarDecimales();
        $run = self::insertar('caja_it_runs', ['caso' => $caso]);
        // Bloques disjuntos de cuatro años, alejados de los días 2020+ de concurrencia.
        $anio = 3000 + $run * 4;
        Runtime::comprobar($anio <= 9996, 'STOP: se agotó el espacio de períodos sintéticos; no se reutiliza automáticamente.');
        $desde = sprintf('%04d-01-01', $anio - 1); $hasta = sprintf('%04d-12-31', $anio + 2);
        Runtime::comprobar(!DB::table('cierre_cajas')->whereBetween('fechaInicio', [$desde.' 00:00:00', $hasta.' 23:59:59'])->exists()
            && !DB::table('cierre_cajas')->whereBetween('fecha_operativa', [$desde, $hasta])->exists()
            && !DB::table('ventas')->whereBetween('fecha_hora', [$desde.' 00:00:00', $hasta.' 23:59:59'])->exists(),
            'STOP: el período reservado ya contiene fixtures ajenos.');
        Runtime::actor();
        Runtime::reloj(($anio + 1).'-01-02 12:00:00');
        $resumen = match ($caso) {
            'conso-decimales' => $prueba->decimales($anio),
            'conso-versiones' => $prueba->versiones($anio),
            'conso-completo' => $prueba->completo($anio),
            'conso-incompleto' => $prueba->incompleto($anio),
            'conso-futuros' => $prueba->futuros($anio),
            'conso-consistencia' => $prueba->consistencia($anio),
        };
        Runtime::comprobar(!(new CoordinadorCobrosCaja)->permiteCierreDefinitivo(), 'Se alteró el gate real.');

        return ['caso' => $caso, 'estado' => 'PASS', 'tipo' => 'consolidado', 'anio_sintetico' => $anio,
            'decimal_mariadb_verificado' => $tiposVerificados, 'integridad_sin_escrituras' => true] + $resumen;
    }

    private static function insertar(string $tabla, array $datos): int
    {
        Guard::autorizarFixtures(); // Consulta real de base antes de cada escritura en MariaDB.
        return (int)DB::table($tabla)->insertGetId($datos);
    }

    private static function verificarDecimales(): void
    {
        $columnas = DB::select('SELECT COLUMN_NAME, DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [Guard::BASE, 'cierre_caja_versiones']);
        $porNombre = [];
        foreach ($columnas as $c) $porNombre[$c->COLUMN_NAME] = $c;
        foreach (['total_ventas', 'efectivo_esperado', 'qr_registrado', 'efectivo_contado', 'diferencia'] as $nombre) {
            $c = $porNombre[$nombre] ?? null;
            Runtime::comprobar($c && $c->DATA_TYPE === 'decimal' && (int)$c->NUMERIC_SCALE === 2
                && (int)$c->NUMERIC_PRECISION === ($nombre === 'diferencia' ? 17 : 16), 'STOP: tipos DECIMAL incompatibles.');
        }
        Runtime::comprobar(stripos($porNombre['diferencia']->EXTRA, 'STORED GENERATED') !== false, 'STOP: diferencia no es generada persistida.');
        $motor = DB::selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [Guard::BASE, 'cierre_caja_versiones']);
        Runtime::comprobar($motor && strcasecmp($motor->ENGINE, 'InnoDB') === 0, 'STOP: se requiere InnoDB.');
    }

    private function cierre(string $fecha, string $total = '0.00', string $cash = '0.00', string $qr = '0.00', string $contado = '0.00', bool $detalle = false, bool $oficial = true): array
    {
        $cierre = self::insertar('cierre_cajas', ['idUsuario' => 1, 'fecha_operativa' => $oficial ? $fecha : null,
            'fechaInicio' => $fecha.' 04:00:00', 'observacion' => 'Fixture sintético consolidación']);
        $version = self::insertar('cierre_caja_versiones', ['cierre_caja_id' => $cierre, 'numero_version' => 1,
            'responsable_id' => 1, 'registrado_en' => $fecha.' 23:00:00', 'total_ventas' => $total,
            'efectivo_esperado' => $cash, 'qr_registrado' => $qr, 'efectivo_contado' => $contado]);
        if ($detalle) {
            $venta = $this->venta($fecha, $total, $cash, $qr);
            self::insertar('cierre_caja_version_ventas', ['cierre_caja_version_id' => $version, 'venta_id' => $venta,
                'fecha_cobro' => $fecha.' 16:00:00', 'metodo_pago' => $qr === '0.00' ? 'Efectivo' : 'Mixto',
                'total' => $total, 'monto_efectivo' => $cash, 'monto_qr' => $qr]);
        }
        self::insertar('bitacoras', ['tablaAfectada' => 'cierre_caja_versiones', 'idRegistroAfectado' => $version,
            'accion' => 'CIERRE_CAJA', 'valoresNuevos' => '{"fixture_sintetico":true}', 'idUsuario' => 1,
            'fechaHora' => '2026-10-26 12:00:00']);
        return [$cierre, $version];
    }

    private function venta(string $fecha, string $total, string $cash, string $qr): int
    {
        $pedido = self::insertar('pedidos', ['usuario_id' => 1, 'fechaHora' => $fecha.' 16:00:00', 'total' => $total, 'estado' => 'Pagado']);
        return self::insertar('ventas', ['pedido_id' => $pedido, 'usuario_id' => 1, 'fecha_hora' => $fecha.' 16:00:00',
            'total' => $total, 'monto_efectivo' => $cash, 'monto_qr' => $qr, 'monto_recibido' => $cash,
            'cambio' => '0.00', 'metodo_pago' => $qr === '0.00' ? 'Efectivo' : 'Mixto', 'estado' => 'Completado']);
    }

    public function sinEscrituras(Closure $consulta, bool $anual = false): array
    {
        Guard::autorizarFixtures();
        $antes = $this->huellas(); $this->lecturasFinancieras = 0; $this->vigilando = true;
        try { $resultado = $consulta(); }
        finally { $this->vigilando = false; }
        Runtime::comprobar($antes === $this->huellas(), 'Las consultas modificaron contenido de las tablas protegidas.');
        if ($anual) Runtime::comprobar($this->lecturasFinancieras === 1, 'El anual no usó una sola lectura financiera.');
        return $resultado;
    }

    private function huellas(): array
    {
        $huellas = [];
        foreach (self::TABLAS as $tabla) {
            $hash = hash_init('sha256');
            $clave = $tabla === 'rol_permisos' ? 'idRol' : 'id';
            $q = DB::table($tabla)->orderBy($clave);
            if ($tabla === 'rol_permisos') $q->orderBy('idPermiso');
            foreach ($q->cursor() as $fila) hash_update($hash, json_encode($fila, JSON_THROW_ON_ERROR)."\n");
            $huellas[$tabla] = hash_final($hash);
        }
        return $huellas;
    }

    private function mensual(int $anio, int $mes): array
    {
        return $this->sinEscrituras(fn () => (new ConsolidacionCajaService)->mensual($anio, $mes));
    }

    private function anual(int $anio): array
    {
        return $this->sinEscrituras(fn () => (new ConsolidacionCajaService)->anual($anio), true);
    }

    private function decimales(int $anio): array
    {
        [, $version] = $this->cierre($anio.'-01-01', '99999999999999.99', '99999999999999.98', '0.01', '99999999999999.97');
        $this->cierre($anio.'-01-02', '99999999999999.99', '99999999999999.98', '0.01', '99999999999999.99');
        $this->cierre($anio.'-01-03', '0.01', '0.00', '0.01', '0.00');
        $raw = DB::table('cierre_caja_versiones')->where('id', $version)->first();
        Runtime::comprobar(is_string($raw->total_ventas) && $raw->total_ventas === '99999999999999.99'
            && is_string($raw->diferencia) && $raw->diferencia === '-0.01', 'DECIMAL no fue devuelto exactamente como cadena.');
        $mes = $this->mensual($anio, 1); $anual = $this->anual($anio);
        foreach (['total_ventas' => '199999999999999.99', 'efectivo_esperado' => '199999999999999.96',
            'qr_registrado' => '0.03', 'efectivo_contado' => '199999999999999.96', 'diferencia' => '0.00',
            'faltantes' => '-0.01', 'sobrantes' => '0.01'] as $campo => $valor) {
            Runtime::comprobar($mes['totales'][$campo] === $valor, 'Acumulado DECIMAL grande o centavos incorrectos.');
        }
        Runtime::comprobar($mes['dias_con_diferencia'] === 2 && $mes['conciliacion'] === 'con_diferencias'
            && $mes['totales'] === $anual['totales'], 'Se ocultaron diferencias o cambió el total anual.');
        return ['cierres' => 3, 'decimales_maximos' => true];
    }

    private function versiones(int $anio): array
    {
        [$cierre, $base] = $this->cierre($anio.'-01-01', '12.35', '12.35', '0.00', '12.35', true);
        $antes = DB::table('cierre_caja_versiones')->where('id', $base)->first();
        $detalle = DB::table('cierre_caja_version_ventas')->where('cierre_caja_version_id', $base)->first();
        foreach ([2 => '11.35', 3 => '12.36'] as $numero => $contado) {
            $nueva = self::insertar('cierre_caja_versiones', ['cierre_caja_id' => $cierre, 'numero_version' => $numero,
                'responsable_id' => 1, 'registrado_en' => $anio.'-01-02 12:00:00', 'total_ventas' => '12.35',
                'efectivo_esperado' => '12.35', 'qr_registrado' => '0.00', 'efectivo_contado' => $contado,
                'motivo_correccion' => 'Rectificación sintética del fixture']);
            self::insertar('cierre_caja_version_ventas', ['cierre_caja_version_id' => $nueva, 'venta_id' => $detalle->venta_id,
                'fecha_cobro' => $detalle->fecha_cobro, 'metodo_pago' => $detalle->metodo_pago,
                'total' => '12.35', 'monto_efectivo' => '12.35', 'monto_qr' => '0.00']);
        }
        $this->cierre($anio.'-01-02', '777.77', '777.77', '0.00', '777.77', true, false);
        $mes = $this->mensual($anio, 1); $anual = $this->anual($anio);
        Runtime::comprobar($mes['totales']['total_ventas'] === '12.35' && $mes['totales']['efectivo_contado'] === '12.36'
            && $mes['totales']['diferencia'] === '0.01' && $mes['totales']['cantidad_ventas'] === 1
            && $mes['dias_rectificados'] === 1 && $mes['dias'][0]['numero_version'] === 3
            && $anual['totales'] === $mes['totales'], 'Se sumaron versiones históricas o cierres no oficiales.');
        Runtime::comprobar($antes == DB::table('cierre_caja_versiones')->where('id', $base)->first()
            && $detalle == DB::table('cierre_caja_version_ventas')->where('id', $detalle->id)->first(), 'Cambió el historial inicial.');
        return ['versiones' => 3, 'oficiales_incluidos' => 1, 'historicos_excluidos' => 1];
    }

    private function completo(int $anio): array
    {
        $cantidad = 0;
        for ($dia = CarbonImmutable::parse($anio.'-01-01'); $dia->year === $anio; $dia = $dia->addDay()) {
            $this->cierre($dia->toDateString()); $cantidad++;
        }
        $anual = $this->anual($anio);
        Runtime::comprobar($anual['estado'] === 'completo' && $anual['conciliacion'] === 'conciliado'
            && $anual['dias_cerrados'] === $cantidad && $anual['fechas_sin_cierre'] === [], 'El año completo no concilia.');
        foreach ($anual['meses'] as $mes) Runtime::comprobar($mes['estado'] === 'completo'
            && $mes['totales'] === $this->mensual($anio, $mes['mes'])['totales'], 'Mes completo inconsistente.');
        return ['dias_cerrados' => $cantidad, 'meses_completos' => 12];
    }

    private function incompleto(int $anio): array
    {
        $this->cierre($anio.'-01-01');
        $this->cierre($anio.'-01-03', '0.03', '0.03', '0.00', '0.02', true);
        $this->venta($anio.'-01-04', '10.00', '10.00', '0.00'); // Venta sin cierre: no se suma.
        Runtime::reloj($anio.'-01-15 12:00:00');
        $mes = $this->mensual($anio, 1); $anual = $this->anual($anio);
        Runtime::comprobar($mes['estado'] === 'incompleto' && $mes['dias_exigibles'] === 14
            && $mes['dias_cerrados'] === 2 && count($mes['fechas_sin_cierre']) === 12
            && $mes['dias'][1]['totales'] === null && $mes['dias_abiertos'] === 1 && $mes['dias_futuros'] === 16
            && $mes['totales']['total_ventas'] === '0.03' && $mes['totales']['diferencia'] === '-0.01'
            && $anual['totales'] === $mes['totales'], 'Cobertura incompleta o exclusión de ventas sin cierre incorrecta.');
        return ['pendientes' => 12, 'dias_abiertos' => 1, 'dias_futuros' => 16];
    }

    private function futuros(int $anio): array
    {
        Runtime::reloj($anio.'-01-31 23:30:00'); // Ya es febrero UTC, pero todavía enero en Bolivia.
        $enero = $this->mensual($anio, 1); $febrero = $this->mensual($anio, 2);
        $futuro = $this->anual($anio + 1); $vacio = $this->anual($anio - 1);
        Runtime::comprobar($enero['fecha_corte'] === $anio.'-01-31' && $enero['dias_abiertos'] === 1
            && count($enero['fechas_sin_cierre']) === 30 && $febrero['estado'] === 'futuro'
            && $febrero['fechas_sin_cierre'] === [] && $futuro['estado'] === 'futuro'
            && $futuro['fechas_sin_cierre'] === [] && $vacio['estado'] === 'incompleto'
            && $vacio['dias_cerrados'] === 0, 'Períodos futuros, vacíos o corte Bolivia incorrectos.');
        return ['corte_bolivia' => true, 'anio_futuro' => true];
    }

    private function consistencia(int $anio): array
    {
        $this->cierre(($anio - 1).'-12-31', '0.01', '0.01', '0.00', '0.01', true);
        foreach ([1 => '0.10', 2 => '0.20', 12 => '0.30'] as $mes => $valor) {
            $this->cierre(sprintf('%04d-%02d-01', $anio, $mes), $valor, $valor, '0.00', $valor, true);
        }
        $anual = $this->anual($anio); $suma = TotalesCajaPeriodo::cero();
        foreach ($anual['meses'] as $mes) {
            $mensual = $this->mensual($anio, $mes['mes']);
            $diario = TotalesCajaPeriodo::cero();
            foreach ($mensual['dias'] as $dia) if ($dia['totales'] !== null) $diario = TotalesCajaPeriodo::sumar($diario, $dia['totales']);
            Runtime::comprobar($diario === $mensual['totales'] && $mensual['totales'] === $mes['totales'], 'Diario y mensual no coinciden.');
            $suma = TotalesCajaPeriodo::sumar($suma, $mensual['totales']);
        }
        Runtime::comprobar($suma === $anual['totales'] && $anual['totales']['total_ventas'] === '0.60'
            && $anual['totales']['cantidad_ventas'] === 3
            && $this->anual($anio - 1)['totales']['total_ventas'] === '0.01', 'El anual duplicó meses o mezcló años.');
        return ['meses_contrastados' => 12, 'consistencia_diaria_mensual_anual' => true];
    }
}
