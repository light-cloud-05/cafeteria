<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\{Bitacora, CierreCaja, CierreCajaVersion};
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final class CierreDiarioService
{
    private const ZONA = DiaOperativoCaja::ZONA;

    private readonly CoordinacionCobrosCaja $coordinacion;
    private readonly AutorizacionCaja $autorizacion;

    public function __construct(?CoordinacionCobrosCaja $coordinacion = null, ?AutorizacionCaja $autorizacion = null)
    {
        $this->coordinacion = $coordinacion ?? CoordinadorCobrosCaja::resolver();
        $this->autorizacion = $autorizacion ?? new AutorizacionCaja;
    }

    /** Lectura informativa; nunca se utiliza este resultado como entrada de confirmar(). */
    public function previsualizar(string $fecha): array
    {
        $this->autorizacion->consulta();
        $dia = $this->dia($fecha, false);

        return $this->calcular($dia, false)['resumen'];
    }

    /**
     * BLOQUEADO por defecto hasta certificar la concurrencia real en MariaDB.
     * No recibe totales, diferencias, listas de ventas ni identificadores de actor.
     */
    public function confirmar(string $fecha, mixed $efectivoContado, ?string $observacion = null): CierreCajaVersion
    {
        $actorPrevio = $this->autorizacion->confirmacion();
        $this->dia($fecha, true);
        try {
            $contado = ImporteCaja::centavos($efectivoContado);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['efectivo_contado' => $e->getMessage()]);
        }
        $observacion = trim($observacion ?? '');
        if (mb_strlen($observacion) > 4000) {
            throw ValidationException::withMessages(['observacion' => 'La observación admite hasta 4000 caracteres.']);
        }
        if (!$this->coordinacion->permiteCierreDefinitivo()) {
            throw new LogicException('Confirmación deshabilitada: falta certificar la coordinación real entre cobros y cierres en MariaDB.');
        }
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('La coordinación debe adquirirse antes de iniciar la transacción.');
        }

        try {
            return $this->coordinacion->exclusivo(function () use ($fecha, $contado, $observacion, $actorPrevio) {
                if (DB::transactionLevel() !== 0) {
                    throw new LogicException('El coordinador no debe envolver el cierre en otra transacción.');
                }
                return DB::transaction(function () use ($fecha, $contado, $observacion, $actorPrevio) {
                    // Revalidar tras cualquier espera por el coordinador.
                    $dia = $this->dia($fecha, true);
                    $actor = $this->autorizacion->revalidarConfirmacion($actorPrevio);
                    if (CierreCaja::where('fecha_operativa', $fecha)->lockForUpdate()->exists()) {
                        throw ValidationException::withMessages(['fecha' => 'La fecha ya tiene un cierre oficial.']);
                    }

                    $calculo = $this->calcular($dia, true);
                    $resumen = $calculo['resumen'];
                    $diferencia = ImporteCaja::decimal($contado - ImporteCaja::centavos($resumen['efectivo_esperado']));
                    if ($diferencia !== '0.00' && $observacion === '') {
                        throw ValidationException::withMessages(['observacion' => 'Explica el faltante o sobrante de efectivo.']);
                    }

                    $cierre = CierreCaja::create([
                        'fecha_operativa' => $fecha,
                        // Los campos históricos no tienen cast: se guardan aquí explícitamente en UTC.
                        'fechaInicio' => $dia->utc()->format('Y-m-d H:i:s'),
                        'fechaFin' => $dia->addDay()->utc()->format('Y-m-d H:i:s'), // extremo exclusivo
                        'idUsuario' => $actor->getKey(),
                    ]);
                    // Los importes oficiales están en versiones; no duplicarlos en columnas heredadas.
                    $version = $cierre->versiones()->create([
                        'numero_version' => 1, 'responsable_id' => $actor->getKey(),
                        'registrado_en' => DiaOperativoCaja::ahora(),
                        'total_ventas' => $resumen['total_ventas'],
                        'efectivo_esperado' => $resumen['efectivo_esperado'],
                        'qr_registrado' => $resumen['qr_registrado'],
                        'efectivo_contado' => ImporteCaja::decimal($contado),
                        'observacion' => $observacion === '' ? null : $observacion,
                    ]);
                    $version->detalles()->createMany($calculo['instantaneas']);
                    if ($version->detalles()->count() !== $resumen['cantidad_ventas']) {
                        throw new LogicException('No se conservaron todas las ventas del cierre.');
                    }
                    $version->refresh();
                    if ($version->diferencia !== $diferencia) {
                        throw new LogicException('La diferencia calculada por la base no coincide con el cálculo exacto.');
                    }
                    $evento = Bitacora::create([
                        'tablaAfectada' => 'cierre_caja_versiones', 'idRegistroAfectado' => $version->id,
                        'accion' => 'CIERRE_CAJA', 'valoresAnteriores' => null,
                        'valoresNuevos' => json_encode($resumen + [
                            'numero_version' => 1, 'efectivo_contado' => ImporteCaja::decimal($contado),
                            'diferencia' => $diferencia,
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'fechaHora' => DiaOperativoCaja::ahora(), 'idUsuario' => $actor->getKey(),
                    ]);
                    if (!$evento->exists) {
                        throw new LogicException('No se conservó la auditoría del cierre.');
                    }

                    return $version->load('detalles');
                }, 1);
            });
        } catch (UniqueConstraintViolationException $e) {
            // No encubrir fallos de unicidad de versiones/detalles como un doble cierre.
            if (str_contains($e->getMessage(), 'cierre_cajas_fecha_operativa_unique')
                || str_contains($e->getMessage(), 'cierre_cajas.fecha_operativa')) {
                throw ValidationException::withMessages(['fecha' => 'La fecha ya tiene un cierre oficial.']);
            }
            throw $e;
        }
    }

    private function dia(string $fecha, bool $definitivo): CarbonImmutable
    {
        if (config('app.timezone') !== self::ZONA) {
            throw new LogicException('Caja requiere la convención horaria America/La_Paz del proyecto.');
        }
        try {
            $dia = CarbonImmutable::createFromFormat('!Y-m-d', $fecha, self::ZONA);
        } catch (\Exception) {
            $dia = null;
        }
        if (!$dia || !preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $fecha) || $dia->format('Y-m-d') !== $fecha) {
            throw ValidationException::withMessages(['fecha' => 'La fecha operativa debe ser una fecha válida YYYY-MM-DD.']);
        }
        $hoy = DiaOperativoCaja::ahora()->startOfDay();
        if ($dia > $hoy || ($definitivo && $dia->equalTo($hoy))) {
            throw ValidationException::withMessages(['fecha' => 'Solo se pueden confirmar días operativos terminados; no fechas futuras.']);
        }

        return $dia;
    }

    private function calcular(CarbonImmutable $dia, bool $bloquear): array
    {
        $desde = $dia->utc()->format('Y-m-d H:i:s');
        $hasta = $dia->addDay()->utc()->format('Y-m-d H:i:s');
        $consulta = DB::table('ventas as v')->leftJoin('pedidos as p', 'p.id', '=', 'v.pedido_id')
            ->where('v.estado', 'Completado')->where('v.fecha_hora', '>=', $desde)->where('v.fecha_hora', '<', $hasta)
            ->select('v.id', 'v.fecha_hora', 'v.metodo_pago', 'v.estado', 'p.id as pedido_id', 'p.estado as estado_pedido')
            // MariaDB devuelve DECIMAL exacto como texto; no hacer sumas PHP con floats.
            ->selectRaw('CAST(v.total AS CHAR) AS total, CAST(v.monto_efectivo AS CHAR) AS efectivo, CAST(v.monto_qr AS CHAR) AS qr, CAST(p.total AS CHAR) AS total_pedido')
            ->orderBy('v.id');
        if ($bloquear) {
            $consulta->lockForUpdate();
        }
        $ventas = $consulta->get();
        $total = $efectivo = $qr = 0;
        $instantaneas = [];
        foreach ($ventas as $venta) {
            try {
                $fechaCobro = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $venta->fecha_hora, 'UTC');
            } catch (\Exception) {
                $fechaCobro = null;
            }
            if (!$fechaCobro || $fechaCobro->format('Y-m-d H:i:s') !== $venta->fecha_hora
                || $fechaCobro->setTimezone(self::ZONA)->toDateString() !== $dia->toDateString()) {
                throw ValidationException::withMessages(['ventas' => "La venta #{$venta->id} no tiene una fecha de cobro válida para este día."]);
            }
            try {
                $importe = ImporteCaja::centavos($venta->total);
                $cash = ImporteCaja::centavos($venta->efectivo);
                $transferencia = ImporteCaja::centavos($venta->qr);
                $pedido = ImporteCaja::centavos($venta->total_pedido);
                $desglose = ImporteCaja::sumar($cash, $transferencia);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['ventas' => "La venta #{$venta->id} contiene importes inválidos."]);
            }
            $metodoValido = match ($venta->metodo_pago) {
                'Efectivo' => $transferencia === 0,
                'QR', 'Transferencia QR' => $cash === 0,
                'Mixto' => $cash > 0 && $transferencia > 0,
                default => false,
            };
            if ($importe !== $desglose || !$metodoValido || $venta->estado !== 'Completado'
                || !$venta->pedido_id || !in_array($venta->estado_pedido, ['Pagado', 'Completado'], true)
                || $pedido !== $importe || $venta->fecha_hora < $desde || $venta->fecha_hora >= $hasta) {
                throw ValidationException::withMessages(['ventas' => "La venta #{$venta->id} es inconsistente; corrígela antes de cerrar."]);
            }
            try {
                $total = ImporteCaja::sumar($total, $importe);
                $efectivo = ImporteCaja::sumar($efectivo, $cash);
                $qr = ImporteCaja::sumar($qr, $transferencia);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['ventas' => $e->getMessage()]);
            }
            $instantaneas[] = [
                'venta_id' => $venta->id,
                // Pasar un objeto UTC al cast evita interpretar la cadena guardada como hora local.
                'fecha_cobro' => $fechaCobro,
                'metodo_pago' => $venta->metodo_pago, 'total' => ImporteCaja::decimal($importe),
                'monto_efectivo' => ImporteCaja::decimal($cash), 'monto_qr' => ImporteCaja::decimal($transferencia),
            ];
        }
        if ($ventas->isNotEmpty() && DB::table('cierre_caja_version_ventas as d')
            ->join('cierre_caja_versiones as ver', 'ver.id', '=', 'd.cierre_caja_version_id')
            ->join('cierre_cajas as c', 'c.id', '=', 'ver.cierre_caja_id')
            ->whereIn('d.venta_id', $ventas->pluck('id'))->where(function ($q) use ($dia) {
                $q->whereNull('c.fecha_operativa')->orWhere('c.fecha_operativa', '<>', $dia->toDateString());
            })->exists()) {
            throw ValidationException::withMessages(['ventas' => 'Hay ventas vinculadas a otro cierre incompatible con esta fecha.']);
        }

        return ['resumen' => [
            'fecha_operativa' => $dia->toDateString(), 'cantidad_ventas' => $ventas->count(),
            'total_ventas' => ImporteCaja::decimal($total), 'efectivo_esperado' => ImporteCaja::decimal($efectivo),
            'qr_registrado' => ImporteCaja::decimal($qr),
        ], 'instantaneas' => $instantaneas];
    }
}
