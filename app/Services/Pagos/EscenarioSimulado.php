<?php

declare(strict_types=1);

namespace App\Services\Pagos;

/** Identificadores de demostración, nunca estados remitidos por un proveedor. */
enum EscenarioSimulado: string
{
    case QR_APROBADO = 'QR_DEMO_APROBADO';
    case QR_RECHAZADO = 'QR_DEMO_RECHAZADO';
    case QR_PENDIENTE = 'QR_DEMO_PENDIENTE';
    case TARJETA_APROBADA = 'TARJETA_DEMO_APROBADA';
    case TARJETA_RECHAZADA = 'TARJETA_DEMO_RECHAZADA';
    case TARJETA_3DS = 'TARJETA_DEMO_3DS_PENDIENTE';
    case EXPIRACION = 'EXPIRACION_DEMO';

    public function permite(string $medio): bool
    {
        return in_array($medio, ['QR_SIMULADO', 'TARJETA_SIMULADA'], true)
            && ($this === self::EXPIRACION || str_starts_with($this->value, $medio === 'QR_SIMULADO' ? 'QR_' : 'TARJETA_'));
    }

    public function resultado(): EstadoPago
    {
        return match ($this) {
            self::QR_APROBADO, self::TARJETA_APROBADA => EstadoPago::APROBADO,
            self::QR_RECHAZADO, self::TARJETA_RECHAZADA => EstadoPago::RECHAZADO,
            self::QR_PENDIENTE, self::TARJETA_3DS => EstadoPago::PENDIENTE,
            self::EXPIRACION => EstadoPago::EXPIRADO,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::QR_APROBADO => 'QR de prueba aprobado',
            self::QR_RECHAZADO => 'QR de prueba rechazado',
            self::QR_PENDIENTE => 'QR de prueba pendiente',
            self::TARJETA_APROBADA => 'Tarjeta de prueba aprobada',
            self::TARJETA_RECHAZADA => 'Tarjeta de prueba rechazada',
            self::TARJETA_3DS => 'Autenticación ficticia 3-D Secure pendiente',
            self::EXPIRACION => 'Simular vencimiento ficticio',
        };
    }
}
