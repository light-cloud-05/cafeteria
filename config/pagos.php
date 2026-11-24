<?php

return [
    'simulacion' => [
        'activa' => env('PAGOS_SIMULACION_ACTIVA', false),
        // Escenario exclusivo del backend; nunca tomar este valor del request.
        'resultado' => env('PAGOS_SIMULACION_RESULTADO', 'PENDIENTE'),
        'vigencia_segundos' => 300,
    ],
];
