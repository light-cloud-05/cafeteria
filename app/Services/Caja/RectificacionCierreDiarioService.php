<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\{CierreCaja, CierreCajaVersion, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Throwable;

final class RectificacionCierreDiarioService
{
    private readonly CoordinacionCobrosCaja $coordinacion;

    public function __construct(?CoordinacionCobrosCaja $coordinacion = null,
        private readonly AutorizacionCaja $autorizacion = new AutorizacionCaja,
        private readonly AuditoriaFinancieraCaja $auditoria = new AuditoriaFinancieraCaja)
    {
        $this->coordinacion = $coordinacion ?? CoordinadorCobrosCaja::resolver();
    }

    public function rectificar(int $cierreId, int $versionEsperada, mixed $efectivoContado,
        string $motivo, ?string $observacion = null): CierreCajaVersion
    {
        $op = new OperacionRectificacionCaja($cierreId, $versionEsperada);
        $identidad = Auth::user();
        if ($identidad instanceof User && $identidad->exists) {
            $op->actorId = (int) $identidad->getKey();
        }
        try {
            $previo = $this->autorizacion->rectificacion();
            $op->actorId = (int) $previo->getKey();
            if ($cierreId < 1 || $versionEsperada < 1 || $versionEsperada >= 4_294_967_295) {
                throw ValidationException::withMessages(['version_esperada' => 'Cierre o versión esperada fuera de rango.']);
            }
            try {
                $contado = ImporteCaja::centavos($efectivoContado);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['efectivo_contado' => $e->getMessage()]);
            }
            $motivo = $this->texto($motivo, 'motivo', 1000, true);
            $observacion = $this->texto($observacion ?? '', 'observacion', 4000, false);
            $observacion = $observacion === '' ? null : $observacion;
            $op->motivo = $motivo;
            if (!$this->coordinacion->permiteCierreDefinitivo()) {
                throw ValidationException::withMessages(['caja' => 'La rectificación financiera continúa deshabilitada en producción.']);
            }

            return $this->coordinacion->exclusivo(function () use ($op, $previo, $contado, $motivo, $observacion) {
                if (DB::transactionLevel() !== 0) {
                    throw new LogicException('La coordinación debe preceder a la transacción de rectificación.');
                }
                $conexion = DB::connection();
                $version = $conexion->transaction(function () use ($conexion, $op, $previo, $contado, $motivo, $observacion) {
                    $op->iniciada = true;
                    $pdo = $conexion->getPdo();
                    $conexion->afterRollBack(static function () use ($op, $conexion, $pdo) {
                        if ($op->cuerpoFallo && $op->activaAlFallar && $conexion->getRawPdo() === $pdo) {
                            try {
                                if (!$pdo->inTransaction()) {
                                    $pdo->query('SELECT 1');
                                    $op->rollbackConfirmado = true;
                                }
                            } catch (Throwable) {
                                // La ausencia de transacción por pérdida de conexión NO prueba rollback.
                            }
                        }
                    });
                    try {
                        $actor = $this->autorizacion->revalidarRectificacion($previo); // Rol -> usuario -> permisos.
                        $cierre = CierreCaja::whereKey($op->cierreId)->lockForUpdate()->firstOrFail();
                        $op->cierreVerificado = true;
                        $op->fecha = $cierre->fecha_operativa?->toDateString();
                        if (!$op->fecha || $op->fecha >= DiaOperativoCaja::ahora()->toDateString()) {
                            throw ValidationException::withMessages(['cierre' => 'Solo se rectifican cierres oficiales de días terminados.']);
                        }
                        $base = $cierre->versiones()->orderByDesc('numero_version')->lockForUpdate()->firstOrFail();
                        $detalles = $base->detalles()->orderBy('id')->lockForUpdate()->get();
                        $op->antes = $this->resumen($base, $detalles->count());
                        if ($base->numero_version !== $op->versionEsperada) {
                            throw ValidationException::withMessages(['version_esperada' => 'El cierre ya cambió: consulta su versión vigente.']);
                        }
                        $instantaneas = $this->instantaneas($base, $detalles, $op->fecha);
                        if ($base->efectivo_contado === ImporteCaja::decimal($contado) && $base->observacion === $observacion) {
                            throw ValidationException::withMessages(['rectificacion' => 'No hay efectivo ni observación que rectificar.']);
                        }
                        $op->despues = [
                            'numero_version' => $base->numero_version + 1, 'responsable_id' => $actor->getKey(),
                            'total_ventas' => $base->total_ventas, 'efectivo_esperado' => $base->efectivo_esperado,
                            'qr_registrado' => $base->qr_registrado, 'efectivo_contado' => ImporteCaja::decimal($contado),
                            'diferencia' => ImporteCaja::decimal($contado - ImporteCaja::centavos($base->efectivo_esperado)),
                            'cantidad_ventas' => $detalles->count(),
                        ];
                        $nueva = $cierre->versiones()->create([
                            'numero_version' => $base->numero_version + 1, 'responsable_id' => $actor->getKey(),
                            'registrado_en' => DiaOperativoCaja::ahora(),
                            'total_ventas' => $base->total_ventas, 'efectivo_esperado' => $base->efectivo_esperado,
                            'qr_registrado' => $base->qr_registrado, 'efectivo_contado' => ImporteCaja::decimal($contado),
                            'observacion' => $observacion, 'motivo_correccion' => $motivo,
                        ]);
                        if (!$nueva->exists) {
                            throw new LogicException('No se creó la versión de rectificación.');
                        }
                        $nueva->detalles()->createMany($instantaneas);
                        if ($nueva->detalles()->count() !== $detalles->count()) {
                            throw new LogicException('No se conservaron todas las instantáneas.');
                        }
                        $nueva->refresh();
                        if ($nueva->diferencia !== ImporteCaja::decimal($contado - ImporteCaja::centavos($base->efectivo_esperado))) {
                            throw new LogicException('La diferencia generada no coincide con el cálculo exacto.');
                        }
                        $op->despues = $this->resumen($nueva, $detalles->count());
                        $op->despues['observacion_modificada'] = $base->observacion !== $observacion;
                        $this->auditoria->confirmada($op);

                        return $nueva->load('detalles');
                    } catch (Throwable $e) {
                        $op->cuerpoFallo = true;
                        try {
                            $op->activaAlFallar = $conexion->getRawPdo() === $pdo && $pdo->inTransaction();
                        } catch (Throwable) {
                            $op->activaAlFallar = false;
                        }
                        throw $e;
                    }
                }, 1);
                $op->commitConfirmado = true;

                return $version;
            });
        } catch (Throwable $e) {
            $this->auditoria->incidente($op, $e);
            throw $e;
        }
    }

    private function resumen(CierreCajaVersion $version, int $cantidad): array
    {
        return ['version_id' => $version->id, 'numero_version' => $version->numero_version,
            'responsable_id' => $version->responsable_id,
            'total_ventas' => $version->total_ventas, 'efectivo_esperado' => $version->efectivo_esperado,
            'qr_registrado' => $version->qr_registrado, 'efectivo_contado' => $version->efectivo_contado,
            'diferencia' => $version->diferencia, 'cantidad_ventas' => $cantidad];
    }

    private function instantaneas(CierreCajaVersion $base, $detalles, string $fecha): array
    {
        $total = $efectivo = $qr = 0;
        $copias = [];
        try {
            foreach ($detalles as $detalle) {
                $t = ImporteCaja::centavos($detalle->total);
                $e = ImporteCaja::centavos($detalle->monto_efectivo);
                $q = ImporteCaja::centavos($detalle->monto_qr);
                $cobro = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $detalle->getRawOriginal('fecha_cobro'), 'UTC');
                if ($t !== ImporteCaja::sumar($e, $q) || !$cobro
                    || $cobro->format('Y-m-d H:i:s') !== $detalle->getRawOriginal('fecha_cobro')
                    || $cobro->setTimezone(DiaOperativoCaja::ZONA)->toDateString() !== $fecha) {
                    throw new InvalidArgumentException('Instantánea histórica inconsistente.');
                }
                $total = ImporteCaja::sumar($total, $t);
                $efectivo = ImporteCaja::sumar($efectivo, $e);
                $qr = ImporteCaja::sumar($qr, $q);
                $copias[] = ['venta_id' => $detalle->venta_id, 'fecha_cobro' => $cobro, 'metodo_pago' => $detalle->metodo_pago,
                    'total' => $detalle->total, 'monto_efectivo' => $detalle->monto_efectivo, 'monto_qr' => $detalle->monto_qr];
            }
            if (ImporteCaja::decimal($total) !== $base->total_ventas || ImporteCaja::decimal($efectivo) !== $base->efectivo_esperado
                || ImporteCaja::decimal($qr) !== $base->qr_registrado) {
                throw new InvalidArgumentException('Los importes históricos no concilian con sus instantáneas.');
            }
        } catch (\Exception) {
            throw ValidationException::withMessages(['cierre' => 'El historial es inconsistente; no puede corregirse reescribiéndolo.']);
        }

        return $copias;
    }

    private function texto(string $valor, string $campo, int $maximo, bool $obligatorio): string
    {
        $valor = trim(str_replace(["\r\n", "\r"], "\n", $valor));
        if (!mb_check_encoding($valor, 'UTF-8') || ($obligatorio && $valor === '') || mb_strlen($valor) > $maximo
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $valor)
            || preg_match('/\b(?:password|contrase(?:ña|na)|token|secret|api[_-]?key|authorization|cookie)\b\s*[:=]|\bBearer\s+\S+/iu', $valor)) {
            throw ValidationException::withMessages([$campo => 'Texto obligatorio, demasiado largo o con datos no admitidos.']);
        }

        return $valor;
    }
}
