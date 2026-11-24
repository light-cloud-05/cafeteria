<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use App\Models\{Pedido, TransaccionPago, User, Venta};
use App\Services\Caja\{AutorizacionCaja, CoordinadorCobrosCaja, DiaOperativoCaja, ImporteCaja};
use App\Services\ReservaPedidoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final class FinalizarCobroService
{
    public function presencial(int $pedidoId, array $datos): Venta
    {
        $autorizacion = app(AutorizacionCaja::class);
        $previo = $autorizacion->cobro();
        return CoordinadorCobrosCaja::resolver()->exclusivo(fn () => DB::transaction(function () use ($pedidoId, $datos, $autorizacion, $previo) {
            $actor = $autorizacion->revalidarCobro($previo);
            $pedido = Pedido::lockForUpdate()->findOrFail($pedidoId);
            BloqueoPagoPedido::verificar($pedido);
            $total = $this->importe($pedido, $datos['version_pedido']);
            $metodo = $datos['metodo_pago'];
            if (!in_array($metodo, ['Efectivo', 'QR', 'Mixto'], true)) $this->error('Método presencial inválido.');
            $qr = $metodo === 'QR' ? $total : ($metodo === 'Mixto' ? $this->centavos($datos['monto_qr'] ?? '') : 0);
            if ($metodo === 'Mixto' && ($qr <= 0 || $qr >= $total)) {
                throw ValidationException::withMessages(['monto_qr' => 'En un pago combinado el QR debe ser mayor a cero y menor al total.']);
            }
            $recibido = $qr + ($metodo === 'QR' ? 0 : $this->centavos($datos['monto_recibido'] ?? ''));
            if ($recibido < $total || $recibido > 99999999) {
                throw ValidationException::withMessages(['monto_recibido' => 'El monto recibido es insuficiente o excede el límite permitido.']);
            }
            return $this->registrar($pedido, $actor, $total, $metodo, $total - $qr, $qr, 0, $recibido, 'OPERACION');
        }, 1));
    }

    /** Interno: dentro de la coordinación, autorización y transacción del servicio de intentos. */
    public function simulado(Pedido $pedido, TransaccionPago $intento, User $actor): Venta
    {
        if (DB::transactionLevel() !== 1) throw new LogicException('La finalización requiere una transacción coordinada.');
        $actor = app(AutorizacionCaja::class)->revalidarCobro($actor);
        $pedido = Pedido::lockForUpdate()->findOrFail($pedido->id);
        $intento = TransaccionPago::lockForUpdate()->findOrFail($intento->id);
        if ($intento->estado !== EstadoPago::PENDIENTE
            || (int)$intento->pedido_id !== (int)$pedido->id || (int)$intento->usuario_id !== (int)$actor->id
            || $intento->origen !== 'SIMULACION_LOCAL' || $intento->moneda !== 'BOB'
            || !in_array($intento->medio, ['QR_SIMULADO', 'TARJETA_SIMULADA'], true)) {
            throw new LogicException('La finalización simulada requiere un intento pendiente bloqueado y autorizado.');
        }
        app(AislamientoPagosSimulados::class)->verificar();
        $total = $this->importe($pedido, $intento->version_pedido);
        if ($total !== $this->centavos($intento->monto) || $intento->vence_en->lessThanOrEqualTo(now())) {
            $this->error('El importe del intento no coincide o su vigencia terminó.');
        }
        return $this->registrar($pedido, $actor, $total, $intento->medio, 0,
            $intento->medio === 'QR_SIMULADO' ? $total : 0,
            $intento->medio === 'TARJETA_SIMULADA' ? $total : 0, $total, 'SIMULACION_LOCAL');
    }

    /** No recibe importes del cliente: usa exclusivamente la instantánea persistida del pedido. */
    public function importe(Pedido $pedido, string $version): int
    {
        if ($pedido->estado !== 'Registrado' || $pedido->venta()->exists()) $this->error('Este pedido ya fue cobrado o no está registrado.');
        if (!hash_equals($pedido->versionCobro(), $version)) $this->error('El pedido cambió. Recarga y confirma su total.');
        $detalles = $pedido->detalles()->orderBy('producto_id')->get();
        if ($detalles->isEmpty()) $this->error('El pedido no tiene productos.');
        $subtotal = 0;
        foreach ($detalles as $detalle) {
            $linea = $this->centavos((string)$detalle->subtotal);
            $cantidad = (int)$detalle->cantidad;
            if ($cantidad < 1 || $cantidad > 10000 || $this->centavos((string)$detalle->precioUnitario) * $cantidad !== $linea) {
                $this->error('El detalle contiene importes o cantidades inconsistentes.');
            }
            $subtotal += $linea;
        }
        $total = $subtotal - $this->centavos((string)$pedido->descuento);
        if ($total < 0 || $total > 99999999 || $subtotal > 99999999 || $this->centavos((string)$pedido->total) !== $total) {
            $this->error('El importe del pedido es inconsistente o está fuera de rango.');
        }
        return $total;
    }

    private function registrar(Pedido $pedido, User $actor, int $total, string $metodo,
        int $efectivo, int $qr, int $tarjeta, int $recibido, string $origen): Venta
    {
        $fecha = DiaOperativoCaja::ahora();
        DiaOperativoCaja::verificarAbierto($fecha);
        app(ReservaPedidoService::class)->mover($pedido);
        $venta = Venta::create([
            'pedido_id' => $pedido->id, 'usuario_id' => $actor->id, 'fecha_hora' => $fecha,
            'metodo_pago' => $metodo, 'total' => ImporteCaja::decimal($total),
            'monto_efectivo' => ImporteCaja::decimal($efectivo), 'monto_qr' => ImporteCaja::decimal($qr),
            'monto_tarjeta' => ImporteCaja::decimal($tarjeta), 'origen' => $origen,
            'monto_recibido' => ImporteCaja::decimal($recibido), 'cambio' => ImporteCaja::decimal($recibido - $total),
            'estado' => 'Completado',
        ]);
        $pedido->update(['estado' => 'Pagado', 'total' => ImporteCaja::decimal($total)]);
        return $venta;
    }

    private function centavos(mixed $importe): int
    {
        try { return ImporteCaja::centavos($importe); }
        catch (InvalidArgumentException) { $this->error('Importe inválido: utiliza hasta dos decimales.'); }
    }

    private function error(string $mensaje): never
    {
        throw ValidationException::withMessages(['pedido' => $mensaje]);
    }
}
