<?php

declare(strict_types=1);

namespace App\Services\Caja;

use Carbon\CarbonImmutable;
use Brick\Math\{BigDecimal, RoundingMode};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

final class ConsolidacionCajaService
{
    public function __construct(private readonly AutorizacionCaja $autorizacion = new AutorizacionCaja) {}

    public function mensual(int $anio, int $mes): array
    {
        $this->autorizacion->consulta();
        $this->validar($anio, $mes);
        $hoy = DiaOperativoCaja::ahora()->toDateString();
        $inicio = CarbonImmutable::create($anio, $mes, 1, 0, 0, 0, DiaOperativoCaja::ZONA);

        return $this->mes($inicio, $hoy, $this->leer($inicio->toDateString(), $inicio->endOfMonth()->toDateString()));
    }

    public function anual(int $anio): array
    {
        $this->autorizacion->consulta();
        $this->validar($anio);
        $hoy = DiaOperativoCaja::ahora()->toDateString();
        // Una sola lectura para los doce meses: no mezclar consultas independientes al rectificar.
        $filas = $this->leer(sprintf('%04d-01-01', $anio), sprintf('%04d-12-31', $anio));
        $meses = []; $totales = TotalesCajaPeriodo::cero();
        $pendientes = $inconsistentes = [];
        $cerrados = $rectificados = $conDiferencia = $exigibles = $futuros = $abiertos = 0;
        foreach (range(1, 12) as $numero) {
            $mes = $this->mes(CarbonImmutable::create($anio, $numero, 1, 0, 0, 0, DiaOperativoCaja::ZONA), $hoy, $filas);
            $meses[] = $mes;
            if ($mes['totales'] !== null && $totales !== null) $totales = TotalesCajaPeriodo::sumar($totales, $mes['totales']);
            else $totales = null;
            $pendientes = array_merge($pendientes, $mes['fechas_sin_cierre']);
            $inconsistentes = array_merge($inconsistentes, $mes['fechas_inconsistentes']);
            $cerrados += $mes['dias_cerrados']; $rectificados += $mes['dias_rectificados'];
            $conDiferencia += $mes['dias_con_diferencia']; $exigibles += $mes['dias_exigibles'];
            $futuros += $mes['dias_futuros']; $abiertos += $mes['dias_abiertos'];
        }
        $estado = $anio > (int)substr($hoy, 0, 4) ? 'futuro'
            : (count(array_filter($meses, fn ($m) => $m['estado'] === 'completo')) === 12 ? 'completo' : 'incompleto');

        return ['anio' => $anio, 'meses' => $meses, 'estado' => $estado, 'totales' => $totales,
            'dias_cerrados' => $cerrados, 'dias_rectificados' => $rectificados, 'dias_con_diferencia' => $conDiferencia,
            'dias_exigibles' => $exigibles, 'dias_futuros' => $futuros, 'dias_abiertos' => $abiertos,
            'fechas_sin_cierre' => $pendientes, 'fechas_inconsistentes' => $inconsistentes,
            'conciliacion' => $this->conciliacion($estado, $cerrados, $conDiferencia, $inconsistentes), 'fecha_corte' => $hoy];
    }

    private function validar(int $anio, ?int $mes = null): void
    {
        if ($anio < 1000 || $anio > 9999) throw ValidationException::withMessages(['anio' => 'Indica un año entre 1000 y 9999.']);
        if ($mes !== null && ($mes < 1 || $mes > 12)) throw ValidationException::withMessages(['mes' => 'Indica un mes entre 1 y 12.']);
    }

    private function leer(string $desde, string $hasta): array
    {
        // LEFT JOIN conserva cabeceras oficiales sin versión para señalarlas como inconsistentes.
        // NOT EXISTS selecciona la versión de número máximo dentro de la misma sentencia SELECT.
        $consulta = DB::table('cierre_cajas as c')->leftJoin('cierre_caja_versiones as v', 'v.cierre_caja_id', '=', 'c.id')
            ->whereNotNull('c.fecha_operativa')->whereBetween('c.fecha_operativa', [$desde, $hasta])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('cierre_caja_versiones as nueva')->whereColumn('nueva.cierre_caja_id', 'c.id')
                    ->whereColumn('nueva.numero_version', '>', 'v.numero_version');
            })->select('c.id as cierre_id', 'c.fecha_operativa', 'v.id as version_id', 'v.numero_version')
            ->selectSub(DB::table('cierre_caja_version_ventas as d')->selectRaw('COUNT(*)')->whereColumn('d.cierre_caja_version_id', 'v.id'), 'cantidad_ventas');
        foreach (array_slice(TotalesCajaPeriodo::CAMPOS, 0, 5) as $campo) $consulta->selectRaw('CAST(v.'.$campo.' AS CHAR) AS '.$campo);
        $filas = [];
        foreach ($consulta->orderBy('c.fecha_operativa')->get() as $fila) {
            // SQLite representa la columna generada con afinidad REAL. Normalizar su texto con
            // BigDecimal igual que el cast decimal:2 de Eloquent; MariaDB ya devuelve DECIMAL exacto.
            if (DB::connection()->getDriverName() === 'sqlite' && $fila->diferencia !== null) {
                $fila->diferencia = (string)BigDecimal::of($fila->diferencia)->toScale(2, RoundingMode::HalfUp);
            }
            if (isset($filas[$fila->fecha_operativa])) throw new UnexpectedValueException('Fecha operativa duplicada.');
            $filas[$fila->fecha_operativa] = $fila;
        }

        return $filas;
    }

    private function mes(CarbonImmutable $inicio, string $hoy, array $filas): array
    {
        $dias = []; $pendientes = $inconsistentes = [];
        $totales = TotalesCajaPeriodo::cero();
        $cerrados = $rectificados = $conDiferencia = $exigibles = $futuros = $abiertos = 0;
        for ($dia = $inicio; $dia <= $inicio->endOfMonth(); $dia = $dia->addDay()) {
            $fecha = $dia->toDateString(); $fila = $filas[$fecha] ?? null;
            $estado = $fecha < $hoy ? 'pendiente' : ($fecha === $hoy ? 'abierto' : 'futuro');
            if ($fecha < $hoy) $exigibles++;
            elseif ($fecha === $hoy) $abiertos++;
            else $futuros++;
            $importes = null;
            if ($fila) {
                try {
                    if ($fecha >= $hoy || !$fila->version_id) throw new UnexpectedValueException('Cierre fuera de plazo o sin versión.');
                    $importes = TotalesCajaPeriodo::version($fila);
                    $totales = TotalesCajaPeriodo::sumar($totales, $importes);
                    $estado = 'cerrado'; $cerrados++;
                    if ((int)$fila->numero_version > 1) $rectificados++;
                    if ($importes['diferencia'] !== '0.00') $conDiferencia++;
                } catch (UnexpectedValueException) {
                    $estado = 'inconsistente'; $inconsistentes[] = $fecha;
                }
            } elseif ($estado === 'pendiente') $pendientes[] = $fecha;
            $dias[] = ['fecha' => $fecha, 'estado' => $estado, 'cierre_id' => $fila?->cierre_id,
                'version_id' => $fila?->version_id, 'numero_version' => $fila ? (int)$fila->numero_version : null, 'totales' => $importes];
        }
        $estado = $inicio->toDateString() > $hoy ? 'futuro'
            : ($inicio->endOfMonth()->toDateString() < $hoy && !$pendientes && !$inconsistentes ? 'completo' : 'incompleto');

        return ['anio' => $inicio->year, 'mes' => $inicio->month, 'estado' => $estado,
            'en_curso' => substr($hoy, 0, 7) === $inicio->format('Y-m'), 'dias' => $dias,
            'totales' => $inconsistentes ? null : $totales, 'dias_cerrados' => $cerrados, 'dias_exigibles' => $exigibles,
            'dias_rectificados' => $rectificados, 'dias_con_diferencia' => $conDiferencia, 'dias_futuros' => $futuros,
            'dias_abiertos' => $abiertos, 'fechas_sin_cierre' => $pendientes, 'fechas_inconsistentes' => $inconsistentes,
            'conciliacion' => $this->conciliacion($estado, $cerrados, $conDiferencia, $inconsistentes), 'fecha_corte' => $hoy];
    }

    private function conciliacion(string $estado, int $cerrados, int $conDiferencia, array $errores): string
    {
        if ($errores) return 'inconsistente';
        if (!$cerrados) return 'sin_cierres';
        if ($conDiferencia) return 'con_diferencias';

        return $estado === 'completo' ? 'conciliado' : 'sin_diferencias_registradas';
    }
}
