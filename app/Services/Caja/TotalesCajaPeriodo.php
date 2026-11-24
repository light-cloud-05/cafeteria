<?php

declare(strict_types=1);

namespace App\Services\Caja;

use Brick\Math\BigDecimal;
use UnexpectedValueException;

/** Acumulados de consulta: pueden superar DECIMAL(16,2), sin cambiar importes diarios. */
final class TotalesCajaPeriodo
{
    public const CAMPOS = ['total_ventas', 'efectivo_esperado', 'qr_registrado', 'efectivo_contado', 'diferencia', 'faltantes', 'sobrantes', 'tarjeta_registrado'];

    public static function cero(): array
    {
        return array_fill_keys(self::CAMPOS, '0.00') + ['cantidad_ventas' => 0];
    }

    public static function version(object $fila): array
    {
        $valores = [];
        foreach (array_merge(array_slice(self::CAMPOS, 0, 5), ['tarjeta_registrado']) as $campo) {
            $valor = $fila->{$campo};
            if (!is_string($valor) || !preg_match('/\A-?[0-9]+(?:\.[0-9]{1,2})?\z/', $valor)) {
                throw new UnexpectedValueException('Importe persistido no válido.');
            }
            $decimal = BigDecimal::of($valor)->toScale(2);
            if ($campo !== 'diferencia' && $decimal->isNegative()) throw new UnexpectedValueException('Importe persistido negativo.');
            $valores[$campo] = (string) $decimal;
        }
        if (!BigDecimal::of($valores['total_ventas'])->isEqualTo(BigDecimal::of($valores['efectivo_esperado'])->plus($valores['qr_registrado'])->plus($valores['tarjeta_registrado']))
            || !BigDecimal::of($valores['diferencia'])->isEqualTo(BigDecimal::of($valores['efectivo_contado'])->minus($valores['efectivo_esperado']))) {
            throw new UnexpectedValueException('Los importes persistidos no concilian.');
        }
        $diferencia = BigDecimal::of($valores['diferencia']);

        return $valores + ['faltantes' => $diferencia->isNegative() ? (string)$diferencia : '0.00',
            'sobrantes' => $diferencia->isPositive() ? (string)$diferencia : '0.00', 'cantidad_ventas' => (int)$fila->cantidad_ventas];
    }

    public static function sumar(array $total, array $sumando): array
    {
        foreach (self::CAMPOS as $campo) $total[$campo] = (string)BigDecimal::of($total[$campo])->plus($sumando[$campo])->toScale(2);
        $total['cantidad_ventas'] += $sumando['cantidad_ventas'];

        return $total;
    }
}
