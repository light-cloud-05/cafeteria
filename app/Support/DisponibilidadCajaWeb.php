<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Caja\CoordinadorCobrosCaja;
use Illuminate\Support\Facades\DB;

final class DisponibilidadCajaWeb
{
    public function habilitada(): bool
    {
        if ((new CoordinadorCobrosCaja)->permiteCierreDefinitivo()) return true;
        // No existe interruptor HTTP, de sesión o de entorno que habilite producción.
        if (PHP_SAPI !== 'cli' || !defined('PHPUNIT_COMPOSER_INSTALL')
            || !app()->runningUnitTests() || !app()->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) return false;
        $conexion = DB::connection();
        if ($conexion->getDriverName() !== 'sqlite' || $conexion->getDatabaseName() !== ':memory:') return false;
        foreach ($conexion->select('PRAGMA database_list') as $base) if ($base->file !== '') return false;

        return CoordinadorCobrosCaja::resolver()->permiteCierreDefinitivo();
    }
}
