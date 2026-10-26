<?php

declare(strict_types=1);

namespace App\Services\Caja;

use InvalidArgumentException;

final class ImporteCaja
{
    // DECIMAL(16,2). Cabe en un entero de 64 bits incluso al sumar/restar dos importes.
    public const MAXIMO_CENTAVOS = 9_999_999_999_999_999;

    public static function centavos(mixed $importe): int
    {
        if ((!is_string($importe) && !is_int($importe))
            || !preg_match('/\A(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,2})?\z/', (string) $importe)) {
            throw new InvalidArgumentException('Se requiere un importe no negativo, sin floats, con hasta dos decimales.');
        }

        [$entero, $decimal] = array_pad(explode('.', (string) $importe, 2), 2, '');

        return ((int) $entero * 100) + (int) str_pad($decimal, 2, '0');
    }

    public static function sumar(int $acumulado, int $importe): int
    {
        if ($acumulado < 0 || $importe < 0 || $acumulado > self::MAXIMO_CENTAVOS - $importe) {
            throw new InvalidArgumentException('El acumulado supera la capacidad monetaria del cierre.');
        }

        return $acumulado + $importe;
    }

    public static function decimal(int $centavos): string
    {
        if ($centavos < -self::MAXIMO_CENTAVOS || $centavos > self::MAXIMO_CENTAVOS) {
            throw new InvalidArgumentException('Importe fuera de la capacidad del cierre.');
        }

        $valor = abs($centavos);

        return ($centavos < 0 ? '-' : '').intdiv($valor, 100).'.'.str_pad((string) ($valor % 100), 2, '0', STR_PAD_LEFT);
    }
}
