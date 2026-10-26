<?php

declare(strict_types=1);

namespace App\Support;

/** Formato de presentación por cadenas; nunca convierte DECIMAL a float. */
final class ImporteCajaVista
{
    public static function formatear(mixed $importe): string
    {
        if (!is_string($importe) || !preg_match('/\A(-?)([0-9]{1,14})\.([0-9]{2})\z/', $importe, $partes)) {
            return 'No disponible';
        }

        return 'Bs '.$partes[1].preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $partes[2]).','.$partes[3];
    }
}
