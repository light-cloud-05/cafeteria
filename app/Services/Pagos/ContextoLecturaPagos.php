<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use Illuminate\Support\Facades\DB;
use Throwable;

/** Las lecturas financieras también deben acreditar el aislamiento de datos ficticios. */
final class ContextoLecturaPagos
{
    public static function esSimulado(object $registro): bool
    {
        return ($registro->origen ?? null) === 'SIMULACION_LOCAL'
            || in_array($registro->metodo_pago ?? null, ['QR_SIMULADO', 'TARJETA_SIMULADA'], true);
    }

    public static function verificar(iterable $registros): bool
    {
        foreach ($registros as $registro) {
            if (self::esSimulado($registro)) { self::exigirDemostracion(); return true; }
        }
        return false;
    }

    public static function exigirDemostracion(): void
    {
        try { app(AislamientoPagosSimulados::class)->verificar(); }
        catch (Throwable) { abort(503, 'Datos simulados no disponibles fuera de una demostración aislada autorizada.'); }
    }

    public static function demostracionDisponible(): bool
    {
        if (config('pagos.simulacion.activa') !== true) return false;
        try { app(AislamientoPagosSimulados::class)->verificar(); return true; }
        catch (Throwable) { return false; }
    }

    public static function verificarCierres(array $ids): bool
    {
        if (!$ids) return false;
        $simuladas = DB::table('cierre_caja_version_ventas as d')
            ->join('cierre_caja_versiones as v', 'v.id', '=', 'd.cierre_caja_version_id')
            ->whereIn('v.cierre_caja_id', $ids)->where(fn ($q) => $q->where('d.origen', 'SIMULACION_LOCAL')
                ->orWhereIn('d.metodo_pago', ['QR_SIMULADO', 'TARJETA_SIMULADA']))->exists();
        if ($simuladas) self::exigirDemostracion();
        return $simuladas;
    }
}
