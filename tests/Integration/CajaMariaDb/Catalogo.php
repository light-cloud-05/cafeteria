<?php

declare(strict_types=1);

namespace Tests\Integration\CajaMariaDb;

/** Cargar el catálogo no abre conexiones ni ejecuta fixtures. */
final class Catalogo
{
    public const CONCURRENCIA = ['dos-cobros', 'cobro-y-cierre', 'cierres-duplicados', 'cobro-durante-cierre',
        'medianoche', 'timeout', 'rollback', 'mismo-pedido', 'fecha-cerrada',
        'rectificaciones-duplicadas', 'rectificacion-y-cobro'];
    public const CONSOLIDADOS = ['conso-decimales', 'conso-versiones', 'conso-completo',
        'conso-incompleto', 'conso-futuros', 'conso-consistencia'];

    public static function casos(string $suite): array
    {
        return match ($suite) {
            'concurrencia' => self::CONCURRENCIA,
            'consolidados' => self::CONSOLIDADOS,
            'ampliada' => [...self::CONCURRENCIA, ...self::CONSOLIDADOS],
            default => throw new \RuntimeException('STOP: suite desconocida.'),
        };
    }
}
