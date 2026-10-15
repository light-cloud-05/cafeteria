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

            <button type="button" class="menu-movil boton secundario" aria-expanded="false" aria-controls="menu-principal">☰ Menú</button>
            <nav id="menu-principal" aria-label="Menú principal">
                <a
                    href="{{ route('home') }}"
                    class="{{ request()->routeIs('home') ? 'activo' : '' }}"
                >
                    Inicio
                </a>
                <a href="{{ route('perfil.contrasena') }}">
    Cambiar mi contraseña
</a>
@if (auth()->user()->tienePermiso('consultar_bitacora'))
    <a href="{{ route('admin.indexbitacora') }}">
        Bitácora
    </a>
@endif

                @if (auth()->user()->tienePermiso('gestion_categorias'))
                    <a
                        href="{{ route('admin.indexcategoria') }}"
                        class="{{ request()->is('admin/categorias*') ? 'activo' : '' }}"
                    >
                        Categorías
                    </a>
                @endif
                @if (auth()->user()->tienePermiso('gestion_usuarios'))
                     <a
                         href="{{ route('admin.indexusuario') }}"
                         class="{{ request()->is('admin/usuarios*') ? 'activo' : '' }}"
                     >
                       Usuarios
                     </a>
                @endif
                @if (auth()->user()->tienePermiso('gestion_roles'))
                     <a
                        href="{{ route('admin.indexrol') }}"
                        class="{{ request()->is('admin/roles*') ? 'activo' : '' }}"
                      >
                        Roles y permisos
                     </a>
                @endif
                @if (auth()->user()->tienePermiso('gestion_productos'))
                     <a
                        href="{{ route('admin.indexproducto') }}"
                        class="{{ request()->is('admin/productos*') ? 'activo' : '' }}"
                      >
                     Productos
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
                @foreach (['consultar_stock'=>['admin.indexstock','Stock actual'], 'consultar_stock_minimo'=>['admin.stock.minimos','Stock mínimo'], 'ver_agotados'=>['admin.stock.agotados','Avisos de agotados'], 'buscar_productos'=>['admin.catalogo','Buscar producto'], 'registrar_entradas'=>['admin.movimiento.crear.entrada','Registrar entrada'], 'registrar_salidas'=>['admin.movimiento.crear.salida','Registrar salida'], 'registrar_ajustes'=>['admin.movimiento.crear.ajuste','Registrar ajuste'], 'registrar_pedidos'=>['admin.pedido.crear','Nuevo pedido'], 'ver_reportes'=>['admin.indexmovimiento','Movimientos']] as $permiso => [$ruta,$texto])
                    @if(auth()->user()->tienePermiso($permiso))<a href="{{ route($ruta) }}">{{ $texto }}</a>@endif
                @endforeach
                @if(auth()->user()->tienePermiso('consultar_historial_pedidos'))<a href="{{ route('admin.indexpedido') }}">Historial de pedidos</a>@endif
                @if(auth()->user()->tienePermiso('cobrar_ventas'))<a href="{{ route('admin.indexventa') }}">Ventas y cobros</a>@endif
                @if(auth()->user()->tienePermiso('ver_reportes'))
                    <a href="{{ route('admin.reportes.diarias') }}">Ventas diarias</a>
                    <a href="{{ route('admin.reportes.pagos') }}">Reporte por método de pago</a>
                    <a href="{{ route('admin.reportes.inventario') }}">Reporte de inventario</a>
                @endif
                @if(auth()->user()->tienePermiso('gestion_promociones'))<a href="{{ route('admin.indexpromocion') }}">Promociones</a>@endif
                @if(!auth()->user()->tienePermiso('gestion_promociones') && (auth()->user()->tienePermiso('registrar_pedidos') || auth()->user()->tienePermiso('modificar_pedidos') || auth()->user()->tienePermiso('cobrar_ventas')))<a href="{{ route('admin.promociones.consultar') }}">Consultar promociones</a>@endif
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

                <button type="button" class="boton secundario" onclick="window.location.reload()">Actualizar</button>
                @yield('contenido')
            </main>
        </div>
    </div>
    <script src="{{ asset('js-borradores.js') }}" defer></script>
    <script>document.querySelector('.menu-movil').addEventListener('click', function () { const abierto=this.getAttribute('aria-expanded')!=='true'; this.setAttribute('aria-expanded', String(abierto)); document.querySelector('.sidebar').classList.toggle('menu-abierto', abierto); });</script>
<script src="{{ asset('js-busqueda.js') }}" defer></script>
</body>
</html>
