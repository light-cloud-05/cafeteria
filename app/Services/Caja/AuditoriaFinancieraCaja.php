<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\{Bitacora, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Validation\ValidationException;
use LogicException;
use PDO;
use ReflectionProperty;
use Throwable;

final class AuditoriaFinancieraCaja
{
    public function confirmada(OperacionRectificacionCaja $op): void
    {
        if (DB::transactionLevel() !== 1 || !$op->actorId || !$op->despues) {
            throw new LogicException('La auditoría confirmada requiere la transacción financiera y su actor.');
        }
        $evento = Bitacora::create([
            'tablaAfectada' => 'cierre_caja_versiones', 'idRegistroAfectado' => $op->despues['version_id'],
            'accion' => 'RECTIFICACION_CAJA', 'idUsuario' => $op->actorId, 'fechaHora' => DiaOperativoCaja::ahora(),
            'valoresAnteriores' => $this->json($this->comun($op) + $this->resumenPermitido($op->antes)),
            'valoresNuevos' => $this->json($this->comun($op) + $this->resumenPermitido($op->despues) + [
                'evento_id' => $op->id.':confirmada', 'resultado' => 'CONFIRMADA', 'motivo' => $op->motivo,
            ]),
        ]);
        if (!$evento->exists) {
            throw new LogicException('No se conservó la auditoría financiera de la rectificación.');
        }
    }

    /** Una sola escritura, fuera de la transacción financiera. Nunca reintenta ni enmascara el error original. */
    public function incidente(OperacionRectificacionCaja $op, Throwable $error): void
    {
        if ($op->incidenteIntentado) {
            return;
        }
        $op->incidenteIntentado = true;
        $persistencia = $op->commitConfirmado ? 'COMMIT_CONFIRMADO'
            : ($op->rollbackConfirmado ? 'ROLLBACK_CONFIRMADO' : ($op->iniciada ? 'INCIERTA' : 'NO_INICIADA'));
        $incierto = $op->commitConfirmado || ($op->iniciada && !$op->rollbackConfirmado)
            || ($error instanceof FalloCoordinacionCaja && $error->resultadoIncierto && !$op->rollbackConfirmado);
        $rechazado = $error instanceof AuthorizationException || $error instanceof AuthenticationException
            || $error instanceof ValidationException || $error instanceof ModelNotFoundException;
        $resultado = $incierto ? 'INCIERTO' : ($rechazado ? 'RECHAZADA' : 'FALLIDA');
        $datos = $this->comun($op) + [
            'evento_id' => $op->id.':incidente', 'resultado' => $resultado, 'persistencia' => $persistencia,
            'codigo' => match (true) {
                $error instanceof AuthorizationException => 'SIN_PERMISO_O_CONTEXTO_CAMBIADO',
                $error instanceof AuthenticationException => 'ACTOR_NO_VALIDO',
                $error instanceof ValidationException => 'VALIDACION_RECHAZADA',
                $error instanceof ModelNotFoundException => 'CIERRE_O_VERSION_INEXISTENTE',
                $error instanceof FalloCoordinacionCaja => 'FALLO_COORDINACION',
                default => 'FALLO_INTERNO',
            },
            'coordinacion_incierta' => $error instanceof FalloCoordinacionCaja && $error->resultadoIncierto,
            // Solo valores verificados después de autorización; nunca copiar entradas inválidas.
            'version_base' => $op->antes ? $this->resumenPermitido($op->antes) : null,
            'version_intentada' => $op->despues ? array_diff_key($this->resumenPermitido($op->despues), ['version_id' => true]) : null,
            'motivo' => $op->motivo,
        ];
        try {
            $conexion = DB::connection();
            $pdo = $conexion->getRawPdo();
            if (!$op->actorId || !$pdo instanceof PDO || $conexion->transactionLevel() !== 0 || $pdo->inTransaction()) {
                throw new LogicException('No hay una sesión independiente utilizable para la bitácora del incidente.');
            }
            $pdo->query('SELECT 1'); // No provocar reconexión con una consulta Laravel.
            $propiedad = new ReflectionProperty(Connection::class, 'reconnector');
            $reconector = $propiedad->getValue($conexion);
            $conexion->setReconnector(static fn () => throw new LogicException('No reconectar para auditar un incidente.'));
            try {
                if (!User::withTrashed()->whereKey($op->actorId)->exists()) {
                    throw new LogicException('El actor ya no existe.');
                }
                $evento = Bitacora::create([
                    'tablaAfectada' => $op->cierreVerificado ? 'cierre_cajas' : 'usuarios',
                    'idRegistroAfectado' => $op->cierreVerificado ? $op->cierreId : $op->actorId,
                    'accion' => 'RECTIFICACION_CAJA_'.$resultado, 'idUsuario' => $op->actorId,
                    'fechaHora' => DiaOperativoCaja::ahora(), 'valoresAnteriores' => null,
                    'valoresNuevos' => $this->json($datos),
                ]);
                if (!$evento->exists) {
                    throw new LogicException('No se conservó el incidente.');
                }
            } finally {
                $propiedad->setValue($conexion, $reconector);
            }
        } catch (Throwable) {
            // Sin actor o sin BD: no inventar IDs ni reconectar. Respaldo estructurado y saneado.
            try {
                Log::warning('caja.rectificacion.incidente', $datos);
            } catch (Throwable) {
                try {
                    error_log('caja.rectificacion.incidente '.$this->json($datos));
                } catch (Throwable) {
                    // Ningún fallo del respaldo debe enmascarar la excepción financiera original.
                }
            }
        }
    }

    private function comun(OperacionRectificacionCaja $op): array
    {
        return ['operacion_id' => $op->id, 'actor_id' => $op->actorId, 'cierre_id' => $op->cierreId,
            'fecha_operativa' => $op->fecha, 'version_esperada' => $op->versionEsperada];
    }

    private function resumenPermitido(array $datos): array
    {
        return array_intersect_key($datos, array_flip(['version_id', 'numero_version', 'responsable_id',
            'total_ventas', 'efectivo_esperado', 'qr_registrado', 'efectivo_contado', 'diferencia',
            'cantidad_ventas', 'observacion_modificada']));
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
