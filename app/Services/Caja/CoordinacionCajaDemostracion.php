<?php

declare(strict_types=1);

namespace App\Services\Caja;

use Closure;

/** Excepción académica explícita; no certifica cierres financieros productivos. */
final class CoordinacionCajaDemostracion implements CoordinacionCobrosCaja
{
    public function __construct(private readonly AislamientoCajaDemo $aislamiento) {}

    public function permiteCierreDefinitivo(): bool
    {
        $this->aislamiento->verificar();
        return true;
    }

    public function exclusivo(Closure $operacion): mixed
    {
        $this->aislamiento->verificar();
        return (new CoordinadorCobrosCaja)->exclusivo(function () use ($operacion) {
            $this->aislamiento->verificar();
            return $operacion();
        });
    }
}
