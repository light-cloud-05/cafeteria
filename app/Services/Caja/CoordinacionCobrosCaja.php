<?php

declare(strict_types=1);

namespace App\Services\Caja;

use Closure;

/**
 * Contrato compartido por VentaController::store() y CierreDiarioService.
 *
 * Debe excluir TODOS los cobros y cierres de esta cafetería, incluso en procesos
 * distintos. Se adquiere ANTES de iniciar transacciones/asignar fecha de cobro
 * y se libera DESPUÉS del commit/rollback, también ante excepciones.
 * No basta con bloquear las ventas existentes ni usar un mutex de este proceso.
 */
interface CoordinacionCobrosCaja
{
    public function exclusivo(Closure $operacion): mixed;

    /** Producción: solo tras certificar el protocolo. Excepción explícita: demo aislada con datos ficticios. */
    public function permiteCierreDefinitivo(): bool;
}
