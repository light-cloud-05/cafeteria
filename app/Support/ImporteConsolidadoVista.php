<?php

declare(strict_types=1);

namespace App\Support;

final class ImporteConsolidadoVista
{
    public static function formatear(?string $valor): string
    {
        if ($valor === null || !preg_match('/\A(-?)([0-9]+)\.([0-9]{2})\z/', $valor, $partes)) return 'No disponible';

        return 'Bs '.$partes[1].preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $partes[2]).','.$partes[3];
    }
}
