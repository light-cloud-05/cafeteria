<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('titulo', 'Inicio') | KARMA Cafetería</title>

    <link rel="stylesheet" href="{{ asset('css/sistema.css') }}">
    <link rel="stylesheet" href="{{ asset('css/modern.css') }}">
</head>
<body>
    <div class="estructura">
        <aside class="sidebar">
            <a class="marca-panel" href="{{ route('home') }}" aria-label="KARMA, ir al inicio">
                <img src="{{ asset('images/karma.png') }}" alt="Cafetería Karma" class="logo-panel">
                <span><strong>KARMA<span class="marca-punto">.</span></strong><small>Gestión de cafetería</small></span>
            </a>

            <button type="button" class="menu-movil boton secundario" aria-expanded="false" aria-controls="menu-principal">☰ Menú</button>
            @include('layouts.menu-paquetes')
            <div class="sidebar-pie"><span class="punto-estado"></span> Un buen día empieza con café.</div>
        </aside>

        <div class="principal">
            <header class="cabecera">
                <div class="perfil-usuario">
                    <span class="avatar-usuario" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->nombreCompleto, 0, 1)) }}</span>
                    <div><strong>{{ auth()->user()->nombreCompleto }}</strong>
                    <small>
                        {{ auth()->user()->rol?->nombre ?? 'Sin rol asignado' }}
                    </small></div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="boton secundario">
                        @include('partials.icono', ['nombre' => 'logout']) Cerrar sesión
                    </button>
                </form>
            </header>

            <main class="contenido">
                @if(auth()->user()->tienePermiso('ver_agotados'))
                    @php($agotados = \App\Models\Stock::whereColumn('stock_actual','<=','stock_minimo')->count())
                    @if($agotados > 0)<div class="aviso error" role="alert">{{ $agotados }} producto(s) requieren reposición. <a href="{{ route('admin.stock.agotados') }}">Consultar avisos</a></div>@endif
                @endif
                @if (session('success'))
                    <div class="aviso exito" role="status">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="aviso error" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="barra-pagina"><div><p class="eyebrow">KARMA / Tu cafetería</p><span class="fecha-panel">{{ now()->locale('es')->translatedFormat('l, d \d\e F') }}</span></div><button type="button" class="boton secundario boton-actualizar" onclick="window.location.reload()">@include('partials.icono', ['nombre' => 'refresh']) Actualizar</button></div>
                @yield('contenido')
            </main>
        </div>
    </div>
    <script src="{{ asset('js-borradores.js') }}" defer></script>
    <script src="{{ asset('js-menu.js') }}" defer></script>
    <script>document.querySelector('.menu-movil').addEventListener('click', function () { const abierto=this.getAttribute('aria-expanded')!=='true'; this.setAttribute('aria-expanded', String(abierto)); document.querySelector('.sidebar').classList.toggle('menu-abierto', abierto); });</script>
<script src="{{ asset('js-busqueda.js') }}" defer></script>
</body>
</html>
