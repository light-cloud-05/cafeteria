@php
    $iconos = [
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>',
        'usuarios' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>',
        'productos' => '<path d="M18 8h1a3 3 0 1 1 0 6h-1M3 8h15v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4ZM6 2v2m4-2v2m4-2v2"/>',
        'inventario' => '<path d="m21 8-9 5-9-5m9 5v9M3 7l9-5 9 5v10l-9 5-9-5Zm4.5-2.5 9 5"/>',
        'pedidos' => '<path d="M2 3h3l3 12h11l3-9H6m3 12h10"/><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/>',
        'reportes' => '<path d="M3 3v18h18M8 16v-5m5 5V7m5 9v-8"/>',
        'promociones' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13m0-13H7a3 3 0 1 1 3-3Zm0 0h5a3 3 0 1 0-3-3Z"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m-7 5h12"/>',
        'refresh' => '<path d="M20 7v5h-5M4 17v-5h5m-4.5-4a8 8 0 0 1 13-4L20 7M4 17l2.5 3a8 8 0 0 0 13-4"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v2"/>',
        'check' => '<path d="m8 12 3 3 5-6"/><circle cx="12" cy="12" r="10"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
@endphp
<svg class="icono {{ $clase ?? '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconos[$nombre] ?? $iconos['productos'] !!}</svg>