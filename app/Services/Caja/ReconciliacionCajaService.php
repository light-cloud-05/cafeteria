<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\{Bitacora, CierreCaja};
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/** Observa registros persistidos. Nunca repite, corrige ni marca una operación como resuelta. */
final class ReconciliacionCajaService
{
    public function __construct(private readonly AutorizacionCaja $autorizacion = new AutorizacionCaja) {}

    public function consultar(string $fecha, ?int $versionEsperada = null, ?string $operacionId = null): array
    {
        $actor = $this->autorizacion->consulta();
        if (!$actor->tienePermiso('consultar_bitacora')) throw new AuthorizationException('La reconciliación requiere consultar caja y bitácora.');
        try { $dia = CarbonImmutable::createFromFormat('!Y-m-d', $fecha, DiaOperativoCaja::ZONA); }
        catch (Throwable) { $dia = null; }
        if (!$dia || !preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $fecha) || $dia->toDateString() !== $fecha) {
            throw ValidationException::withMessages(['fecha' => 'Fecha operativa no válida.']);
        }
        if ($versionEsperada !== null && ($versionEsperada < 1 || $versionEsperada >= 4294967295)) {
            throw ValidationException::withMessages(['version_esperada' => 'Versión esperada fuera de rango.']);
        }
        if ($operacionId !== null && !Str::isUuid($operacionId)) throw ValidationException::withMessages(['operacion_id' => 'Identificador de operación no válido.']);
        $conexion = DB::connection();
        if ($conexion->transactionLevel() !== 0 || $conexion->getPdo()->inTransaction()) throw new LogicException('Reconciliar requiere una consulta fuera de transacciones pendientes.');
        if (in_array($conexion->getDriverName(), ['mysql','mariadb'], true)) {
            if ($conexion->getConfig('read') || $conexion->getConfig('write') || $conexion->hasDirectConnection()) throw new LogicException('Reconciliar requiere la conexión primaria sin separación de lecturas.');
            $aislamiento = strtoupper((string)DB::selectOne('SELECT @@tx_isolation AS aislamiento')->aislamiento);
            if (!in_array($aislamiento, ['READ-COMMITTED','REPEATABLE-READ','SERIALIZABLE'], true)) throw new LogicException('No reconciliar con lecturas no confirmadas.');
        }
        $resultado = ['fecha_operativa' => $fecha, 'observado_en_utc' => DiaOperativoCaja::ahora()->utc()->format('Y-m-d H:i:s'),
            'version_objetivo' => $versionEsperada === null ? 1 : $versionEsperada + 1,
            'estado' => 'SIN_CIERRE_OBSERVADO', 'cierre_id' => null, 'version_vigente_observada' => null,
            'atribucion_solicitud' => 'NO_VERIFICADA', 'versiones' => [], 'reintento_automatico_permitido' => false];
        $cierre = CierreCaja::oficiales()->where('fecha_operativa', $fecha)->first();
        if (!$cierre) return $resultado; // Ausencia observada NO demuestra rollback ni ausencia de operación en curso.
        $resultado['cierre_id'] = $cierre->id;
        $versiones = $cierre->versiones()->with('detalles')->orderBy('numero_version')->get();
        $eventos = Bitacora::where('tablaAfectada', 'cierre_caja_versiones')->whereIn('idRegistroAfectado', $versiones->modelKeys())
            ->whereIn('accion', ['CIERRE_CAJA','RECTIFICACION_CAJA'])
            ->get(['id','idRegistroAfectado','accion','idUsuario','valoresNuevos']);
        $revision = $versiones->isEmpty() || $fecha >= DiaOperativoCaja::ahora()->toDateString(); $objetivo = null;
        foreach ($versiones as $indice => $version) {
            \App\Services\Pagos\ContextoLecturaPagos::verificar($version->detalles);
            $accion = $version->numero_version === 1 ? 'CIERRE_CAJA' : 'RECTIFICACION_CAJA';
            $confirmaciones = $eventos->filter(fn ($evento) => (int)$evento->idRegistroAfectado === $version->id && $evento->accion === $accion);
            $evento = $confirmaciones->count() === 1 ? $confirmaciones->first() : null;
            $meta = $evento ? json_decode($evento->valoresNuevos ?? '', true) : null;
            $operacion = is_array($meta) && is_string($meta['operacion_id'] ?? null) && Str::isUuid($meta['operacion_id']) ? strtolower($meta['operacion_id']) : null;
            $coherente = $this->coherente($version, $fecha) && $version->numero_version === $indice + 1
                && $evento && (int)$evento->idUsuario === (int)$version->responsable_id && is_array($meta)
                && (int)($meta['numero_version'] ?? 0) === $version->numero_version
                && ($meta['fecha_operativa'] ?? null) === $fecha
                && ($version->numero_version === 1 || ($operacion !== null && ($meta['resultado'] ?? null) === 'CONFIRMADA'
                    && ($meta['evento_id'] ?? null) === $operacion.':confirmada'));
            foreach (['total_ventas','efectivo_esperado','qr_registrado','efectivo_contado','diferencia','tarjeta_registrado'] as $campo) {
                $coherente = $coherente && ($meta[$campo] ?? ($campo === 'tarjeta_registrado' ? '0.00' : null)) === $version->{$campo};
            }
            $revision = $revision || !$coherente;
            $fila = ['version_id' => $version->id, 'numero_version' => $version->numero_version,
                'responsable_id' => $version->responsable_id, 'total_ventas' => $version->total_ventas,
                'efectivo_esperado' => $version->efectivo_esperado, 'qr_registrado' => $version->qr_registrado,
                'tarjeta_registrado' => $version->tarjeta_registrado, 'efectivo_contado' => $version->efectivo_contado, 'diferencia' => $version->diferencia,
                'instantaneas' => $version->detalles->count(), 'eventos_confirmados' => $confirmaciones->count(),
                'evento_confirmado_id' => $evento?->id, 'operacion_id' => $operacion, 'coherente' => (bool)$coherente];
            $resultado['versiones'][] = $fila;
            if ($version->numero_version === $resultado['version_objetivo']) $objetivo = $fila;
        }
        $resultado['version_vigente_observada'] = $versiones->last()?->numero_version;
        if ($operacionId !== null) $resultado['atribucion_solicitud'] = $objetivo && $objetivo['operacion_id'] === strtolower($operacionId) ? 'COINCIDE' : 'NO_COINCIDE';
        $resultado['estado'] = $revision || ($operacionId !== null && $resultado['atribucion_solicitud'] !== 'COINCIDE')
            ? 'REQUIERE_REVISION' : ($objetivo ? 'CONFIRMACION_OBSERVADA' : 'VERSION_OBJETIVO_NO_OBSERVADA');

        return $resultado;
    }

    private function coherente($version, string $fecha): bool
    {
        try {
            $total = $cash = $qr = $tarjeta = 0; $ids = [];
            foreach ($version->detalles as $detalle) {
                $t = ImporteCaja::centavos($detalle->total); $e = ImporteCaja::centavos($detalle->monto_efectivo); $q = ImporteCaja::centavos($detalle->monto_qr);
                $card = ImporteCaja::centavos($detalle->monto_tarjeta);
                if ($detalle->origen === 'SIMULACION_LOCAL') app(\App\Services\Pagos\AislamientoPagosSimulados::class)->verificar();
                $cobro = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $detalle->getRawOriginal('fecha_cobro'), 'UTC');
                if (!$cobro || $cobro->format('Y-m-d H:i:s') !== $detalle->getRawOriginal('fecha_cobro')
                    || $cobro->setTimezone(DiaOperativoCaja::ZONA)->toDateString() !== $fecha
                    || $t !== ImporteCaja::sumar(ImporteCaja::sumar($e, $q), $card) || isset($ids[$detalle->venta_id])) return false;
                $ids[$detalle->venta_id] = true;
                $total = ImporteCaja::sumar($total, $t); $cash = ImporteCaja::sumar($cash, $e); $qr = ImporteCaja::sumar($qr, $q); $tarjeta = ImporteCaja::sumar($tarjeta, $card);
            }
            return ImporteCaja::decimal($total) === $version->total_ventas && ImporteCaja::decimal($cash) === $version->efectivo_esperado
                && ImporteCaja::decimal($qr) === $version->qr_registrado && ImporteCaja::decimal($tarjeta) === $version->tarjeta_registrado
                && ImporteCaja::decimal(ImporteCaja::centavos($version->efectivo_contado) - $cash) === $version->diferencia;
        } catch (Throwable) { return false; }
    }
}
