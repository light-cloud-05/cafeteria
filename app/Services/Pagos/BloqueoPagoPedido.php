<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use App\Models\Pedido;
use Illuminate\Validation\ValidationException;

final class BloqueoPagoPedido
{
    /** El llamador debe bloquear el pedido antes de consultar/alterar intentos. */
    public static function verificar(Pedido $pedido): void
    {
        if ($pedido->transaccionesPago()->where('estado', EstadoPago::PENDIENTE->value)->exists()) {
            throw ValidationException::withMessages(['pedido' => 'Hay un intento de pago pendiente. Resuélvelo, cancélalo o expíralo antes de cambiar o cobrar el pedido.']);
        }
    }
}
