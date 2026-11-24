@php
    $trazosPago = match($icono ?? '') {
        'tarjeta' => ['M3 5h18v14H3z','M3 10h18','M7 15h3'],
        'qr' => ['M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3z','M15 15h2v2h-2zM19 15h2M19 19h2v2h-6v-2'],
        'check' => ['m5 12 4 4L19 6'],
        'bolsa' => ['M5 7h14l1 14H4L5 7Z','M9 7V5a3 3 0 0 1 6 0v2'],
        'escudo' => ['m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z','m8 12 3 3 5-6'],
        'reloj' => ['M12 8v5l3 2','M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        'flecha' => ['M5 12h14m-5-5 5 5-5 5'],
        default => ['M12 8v5m0 3h.01','M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
    };
@endphp
<svg class="pago-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">@foreach($trazosPago as $trazo)<path d="{{ $trazo }}"/>@endforeach</svg>
