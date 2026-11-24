<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use App\Models\{Bitacora, Pedido, TransaccionPago};
use App\Services\Caja\{AutorizacionCaja, CoordinadorCobrosCaja, ImporteCaja};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Dominio local: no contiene cliente HTTP, credenciales ni confirmaciones del proveedor. */
final class IntentosPagoService
{
    public function crear(int $pedidoId, string $medio, string $version, string $clave): TransaccionPago
    {
        if (!in_array($medio, ['QR_SIMULADO', 'TARJETA_SIMULADA'], true) || !Str::isUuid($clave)
            || !preg_match('/\A[a-f0-9]{64}\z/', $version)) {
            throw ValidationException::withMessages(['pago' => 'Medio, versión o clave de idempotencia inválidos.']);
        }
        $clave = strtolower($clave);
        return $this->coordinado(function ($actor) use ($pedidoId, $medio, $version, $clave) {
            $pedido = Pedido::lockForUpdate()->findOrFail($pedidoId);
            $existente = TransaccionPago::where('clave_idempotencia', $clave)->lockForUpdate()->first();
            if ($existente) {
                if ((int)$existente->pedido_id !== $pedidoId || (int)$existente->usuario_id !== (int)$actor->id
                    || $existente->medio !== $medio || !hash_equals($existente->version_pedido, $version)) {
                    throw ValidationException::withMessages(['pago' => 'La clave de idempotencia ya identifica otra solicitud.']);
                }
                return $existente; // Incluye terminales: nunca recrear ni reprocesar.
            }
            $finalizar = app(FinalizarCobroService::class);
            $total = $finalizar->importe($pedido, $version);
            $activo = $pedido->transaccionesPago()->where('estado', 'PENDIENTE')->lockForUpdate()->first();
            if ($activo && $activo->vence_en->lessThanOrEqualTo(now())) {
                $this->terminar($activo, EstadoPago::EXPIRADO, 'VIGENCIA_AGOTADA', $actor->id);
            }
            BloqueoPagoPedido::verificar($pedido);
            $vigencia = config('pagos.simulacion.vigencia_segundos');
            if (!is_int($vigencia) || $vigencia < 1 || $vigencia > 3600) throw new LogicException('Vigencia local inválida.');
            $intento = new TransaccionPago;
            $intento->forceFill([
                'pedido_id' => $pedido->id, 'usuario_id' => $actor->id, 'referencia' => (string)Str::uuid(),
                'clave_idempotencia' => $clave, 'medio' => $medio, 'origen' => 'SIMULACION_LOCAL',
                'moneda' => 'BOB', 'monto' => ImporteCaja::decimal($total), 'estado' => EstadoPago::PENDIENTE,
                'version_pedido' => $version, 'vence_en' => now()->addSeconds($vigencia),
            ])->save();
            $this->auditar($intento, $actor->id, 'CREADO');
            return $intento;
        });
    }

    /** El resultado procede exclusivamente de configuración del servidor. No recibe estado del cliente. */
    public function procesar(int $intentoId): TransaccionPago
    {
        return $this->resolver($intentoId, false);
    }

    public function cancelar(int $intentoId): TransaccionPago
    {
        return $this->resolver($intentoId, true);
    }

    public function simular(int $intentoId, EscenarioSimulado $escenario): TransaccionPago
    {
        return $this->resolver($intentoId, false, $escenario);
    }

    private function resolver(int $id, bool $cancelar, ?EscenarioSimulado $escenario = null): TransaccionPago
    {
        return $this->coordinado(function ($actor) use ($id, $cancelar, $escenario) {
            // Orden único: rol -> usuario -> pedido -> intento -> productos, igual que el cobro.
            $pedidoId = TransaccionPago::whereKey($id)->value('pedido_id');
            $pedido = Pedido::lockForUpdate()->findOrFail($pedidoId);
            $intento = TransaccionPago::whereKey($id)->lockForUpdate()->firstOrFail();
            if ((int)$intento->usuario_id !== (int)$actor->id) throw new AuthorizationException('Solo el responsable del intento puede resolverlo.');
            if ($intento->estado !== EstadoPago::PENDIENTE) return $intento;
            if ($intento->vence_en->lessThanOrEqualTo(now())) {
                return $this->terminar($intento, EstadoPago::EXPIRADO, 'VIGENCIA_AGOTADA', $actor->id);
            }
            if ($cancelar) return $this->terminar($intento, EstadoPago::CANCELADO, 'CANCELACION_LOCAL', $actor->id);
            if ($escenario && !$escenario->permite($intento->medio)) {
                throw ValidationException::withMessages(['escenario' => 'El escenario no corresponde al medio de este intento.']);
            }
            // Incluso un escenario pendiente debe corresponder a un pedido e importe vigentes.
            $total = app(FinalizarCobroService::class)->importe($pedido, $intento->version_pedido);
            if ($total !== ImporteCaja::centavos($intento->monto)) {
                throw ValidationException::withMessages(['pago' => 'El importe del intento no coincide con el pedido.']);
            }
            $resultado = $escenario?->resultado() ?? EstadoPago::tryFrom((string)config('pagos.simulacion.resultado'));
            if ($resultado === EstadoPago::EXPIRADO && $escenario === EscenarioSimulado::EXPIRACION) {
                return $this->terminar($intento, $resultado, 'EXPIRACION_DEMO', $actor->id);
            }
            if (!in_array($resultado, [EstadoPago::PENDIENTE, EstadoPago::APROBADO, EstadoPago::RECHAZADO], true)) {
                throw new LogicException('Escenario del simulador no válido.');
            }
            if ($resultado === EstadoPago::PENDIENTE) {
                if ($escenario) $this->auditar($intento, $actor->id, $escenario->value);
                return $intento;
            }
            if ($resultado === EstadoPago::APROBADO) {
                $venta = app(FinalizarCobroService::class)->simulado($pedido, $intento, $actor);
                $intento->venta_id = $venta->id;
                $intento->confirmado_en = now();
            }
            return $this->terminar($intento, $resultado, 'SIMULADOR_'.$resultado->value, $actor->id);
        });
    }

    private function coordinado(\Closure $operacion): TransaccionPago
    {
        $autorizacion = app(AutorizacionCaja::class);
        $previo = $autorizacion->cobro();
        app(AislamientoPagosSimulados::class)->verificar();
        return CoordinadorCobrosCaja::resolver()->exclusivo(fn () => DB::transaction(function () use ($autorizacion, $previo, $operacion) {
            $actor = $autorizacion->revalidarCobro($previo);
            app(AislamientoPagosSimulados::class)->verificar();
            return $operacion($actor);
        }, 1)); // Nunca reintentar automáticamente un resultado incierto.
    }

    private function terminar(TransaccionPago $intento, EstadoPago $estado, string $codigo, int $actorId): TransaccionPago
    {
        $intento->estado->verificarTransicion($estado);
        $intento->estado = $estado;
        $intento->resuelto_en = now();
        $intento->codigo_resultado = $codigo;
        $intento->save();
        $this->auditar($intento, $actorId, $estado->value);
        return $intento;
    }

    private function auditar(TransaccionPago $intento, int $actorId, string $evento): void
    {
        Bitacora::create([
            'tablaAfectada' => 'transacciones_pago', 'idRegistroAfectado' => $intento->id,
            'accion' => 'PAGO_SIMULADO_'.$evento, 'valoresAnteriores' => null,
            'valoresNuevos' => json_encode(['referencia' => $intento->referencia, 'origen' => 'SIMULACION_LOCAL',
                'estado' => $intento->estado->value, 'venta_id' => $intento->venta_id], JSON_THROW_ON_ERROR),
            'fechaHora' => now(), 'idUsuario' => $actorId,
        ]);
    }
}
