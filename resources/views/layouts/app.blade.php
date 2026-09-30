<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('titulo', 'Inicio') | KARMA Cafetería</title>

    <link rel="stylesheet" href="{{ asset('css/sistema.css') }}">
</head>
<body>
    <div class="estructura">
        <aside class="sidebar">
            <h2 class="marca">KARMA</h2>
            <p class="subtitulo">Gestión de cafetería</p>

            <nav aria-label="Menú principal">
                <a
                    href="{{ route('home') }}"
                    class="{{ request()->routeIs('home') ? 'activo' : '' }}"
                >
                    Inicio
                </a>

                @if (auth()->user()->tienePermiso('gestion_categorias'))
                    <a
                        href="{{ route('admin.indexcategoria') }}"
                        class="{{ request()->is('admin/categorias*') ? 'activo' : '' }}"
                    >
                        Categorías
                    </a>
                @endif
                @if (auth()->user()->tienePermiso('gestion_proveedores'))
                     <a
                          href="{{ route('admin.indexproveedor') }}"
                          class="{{ request()->is('admin/proveedores*') ? 'activo' : '' }}"
                         >
                        Proveedores
                     </a>
                 @endif
            </nav>
        </aside>

        <div class="principal">
            <header class="cabecera">
                <div>
                    <strong>{{ auth()->user()->nombreCompleto }}</strong>
                    <small>
                        {{ auth()->user()->rol?->nombre ?? 'Sin rol asignado' }}
                    </small>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="boton secundario">
                        Cerrar sesión
                    </button>
                </form>
            </header>

            <main class="contenido">
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

                @yield('contenido')
            </main>
        </div>
    </div>
</body>
</html>