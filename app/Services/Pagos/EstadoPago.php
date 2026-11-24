<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use LogicException;

enum EstadoPago: string
{
    case PENDIENTE = 'PENDIENTE';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
    case EXPIRADO = 'EXPIRADO';
    case CANCELADO = 'CANCELADO';

    public function verificarTransicion(self $destino): void
    {
        if ($this !== self::PENDIENTE || $destino === self::PENDIENTE) {
            throw new LogicException('Transición de pago inválida: un intento terminal no se reprocesa.');
        }
    }
}
