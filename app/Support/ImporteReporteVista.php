<?php

declare(strict_types=1);

namespace App\Support;

final class ImporteReporteVista
{
    public static function formatear(string $valor): string
    {
        if (!preg_match('/\A([0-9]+)(?:\.([0-9]{1,2}))?\z/', $valor, $m)) return 'No disponible';
        return 'Bs '.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $m[1]).'.'.str_pad($m[2] ?? '',2,'0');
    }
}
