<?php

declare(strict_types=1);

namespace App\Services\Caja;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class DiaOperativoCaja
{
    public const ZONA = 'America/La_Paz';

    public static function ahora(): CarbonImmutable
    {
        if (config('app.timezone') !== self::ZONA) {
            throw new LogicException('Caja requiere America/La_Paz y la convención UTC del proyecto.');
        }

        return CarbonImmutable::now(self::ZONA);
    }

    /** Invocar dentro de la sección coordinada y antes de alterar pedido o existencias. */
    public static function verificarAbierto(CarbonImmutable $fechaCobro): void
    {
        $fecha = $fechaCobro->setTimezone(self::ZONA)->toDateString();
        if (DB::table('cierre_cajas')->where('fecha_operativa', $fecha)->exists()) {
            throw ValidationException::withMessages(['caja' => 'La fecha efectiva del cobro ya tiene un cierre oficial.']);
        }
    }
}
