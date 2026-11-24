<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Caja\AutorizacionCaja;
use App\Services\Pagos\AislamientoPagosSimulados;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class DisponibilidadPagosSimulados
{
    public function habilitada(): bool
    {
        if (config('pagos.simulacion.activa') !== true) return false;
        try {
            app(AutorizacionCaja::class)->cobro();
            app(AislamientoPagosSimulados::class)->verificar();
            return Schema::hasTable('transacciones_pago') && Schema::hasColumn('ventas', 'monto_tarjeta');
        } catch (Throwable) {
            // La UI falla cerrada y nunca publica mensajes PDO, identidades ni grants.
            return false;
        }
    }
}
