<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Services\Pagos\ContextoLecturaPagos;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use UnexpectedValueException;

final class VentasReporteService
{
    public const CAMPOS = ['total', 'monto_efectivo', 'monto_qr', 'monto_tarjeta'];

    public function resumir(Collection $ventas): array
    {
        $simulacion = ContextoLecturaPagos::verificar($ventas);
        $totales = array_fill_keys(self::CAMPOS, '0.00');
        $resumen = collect();
        foreach ($ventas as $venta) {
            $importes = [];
            foreach (self::CAMPOS as $campo) {
                try { $decimal = BigDecimal::of((string)$venta->{$campo})->toScale(2); }
                catch (\Throwable) { throw new UnexpectedValueException('Importe financiero inválido.'); }
                if ($decimal->isNegative()) throw new UnexpectedValueException('Importe financiero negativo.');
                $importes[$campo] = (string)$decimal;
            }
            if (!BigDecimal::of($importes['total'])->isEqualTo(BigDecimal::of($importes['monto_efectivo'])
                ->plus($importes['monto_qr'])->plus($importes['monto_tarjeta']))) {
                throw new UnexpectedValueException('El total no coincide con sus componentes.');
            }
            $simulada = ContextoLecturaPagos::esSimulado($venta);
            $origen = $venta->origen;
            $e = $importes['monto_efectivo']; $q = $importes['monto_qr']; $t = $importes['monto_tarjeta'];
            $valido = match ($venta->metodo_pago) {
                'Efectivo' => $q === '0.00' && $t === '0.00',
                'QR', 'Transferencia QR' => $e === '0.00' && $t === '0.00',
                'Mixto' => $e !== '0.00' && $q !== '0.00' && $t === '0.00',
                'QR_SIMULADO' => $e === '0.00' && $t === '0.00' && $origen === 'SIMULACION_LOCAL',
                'TARJETA_SIMULADA' => $e === '0.00' && $q === '0.00' && $origen === 'SIMULACION_LOCAL',
                default => false,
            };
            if (!$valido || !in_array($origen, ['OPERACION','SIMULACION_LOCAL'], true)
                || ($simulada && !in_array($venta->metodo_pago, ['QR_SIMULADO','TARJETA_SIMULADA'], true))) {
                throw new UnexpectedValueException('Método, origen o componentes inconsistentes.');
            }
            // Una venta Mixto ocupa una sola fila de método. Sus componentes no son ventas nuevas.
            $metodo = $venta->metodo_pago === 'Transferencia QR' ? 'QR' : $venta->metodo_pago;
            $fila = $resumen->get($metodo, ['cantidad'=>0, 'origen'=>$origen] + array_fill_keys(self::CAMPOS,'0.00'));
            $fila['cantidad']++;
            foreach (self::CAMPOS as $campo) {
                $totales[$campo] = (string)BigDecimal::of($totales[$campo])->plus($importes[$campo]);
                $fila[$campo] = (string)BigDecimal::of($fila[$campo])->plus($importes[$campo]);
            }
            $resumen->put($metodo,$fila);
        }
        return compact('resumen','totales','simulacion');
    }
}
