<?php

return [
    'activa' => env('CAJA_DEMO_ACTIVA', false),
    // Identidad del servidor local esperado; no basta con DB_HOST=localhost.
    'servidor' => env('CAJA_DEMO_SERVIDOR', ''),
];
