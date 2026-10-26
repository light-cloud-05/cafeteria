<?php

declare(strict_types=1);

namespace App\Services\Caja;

use RuntimeException;
use Throwable;

final class FalloCoordinacionCaja extends RuntimeException
{
    public function __construct(string $mensaje, public readonly bool $resultadoIncierto = false, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, 0, $anterior);
    }
}
