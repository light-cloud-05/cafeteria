<?php

declare(strict_types=1);

namespace App\Services\Pagos;

use App\Services\Caja\AislamientoCajaDemo;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AislamientoPagosSimulados
{
    public function verificar(): void
    {
        if (config('pagos.simulacion.activa') !== true) {
            throw new LogicException('La simulación de pagos está deshabilitada.');
        }
        // Excepción verificable solo para PHPUnit y bases efímeras, nunca para la web.
        if (PHP_SAPI === 'cli' && defined('PHPUNIT_COMPOSER_INSTALL') && app()->runningUnitTests()
            && app()->environment('testing') && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:'
            && !config('database.connections.sqlite.url')) {
            foreach (DB::select('PRAGMA database_list') as $base) {
                if ($base->file !== '') throw new LogicException('La prueba de pagos exige memoria exclusivamente.');
            }
            return;
        }
        // Reutiliza cuenta DML limitada, identidad, host, puerto y grants de la demo existente.
        app(AislamientoCajaDemo::class)->verificar();
    }
}
