@php
    $usuarioMenu = auth()->user();
    $paquetesMenu = [
        ['id' => 'usuarios', 'codigo' => 'P1', 'nombre' => 'Usuarios', 'opciones' => [
            ['gestion_usuarios', 'admin.indexusuario', 'CU04', 'Gestionar usuarios'],
            ['gestion_usuarios', 'admin.crearusuario', null, 'Crear usuario'],
            ['gestion_roles', 'admin.indexrol', 'CU05', 'Roles y permisos'],
            [null, 'perfil.contrasena', null, 'Cambiar mi contraseña'],
        ]],
        ['id' => 'productos', 'codigo' => 'P2', 'nombre' => 'Productos', 'opciones' => [
            ['gestion_categorias', 'admin.indexcategoria', 'CU06', 'Gestionar categorías'],
            ['gestion_categorias', 'admin.crearcategoria', null, 'Crear categoría'],
            ['gestion_productos', 'admin.indexproducto', 'CU07', 'Gestionar productos'],
            ['gestion_productos', 'admin.crearproducto', null, 'Crear producto'],
            ['buscar_productos', 'admin.catalogo', 'CU14', 'Buscar producto'],
        ]],
        ['id' => 'inventario', 'codigo' => 'P3', 'nombre' => 'Inventario', 'opciones' => [
            ['gestion_proveedores', 'admin.indexproveedor', 'CU08', 'Gestionar proveedores'],
            ['gestion_proveedores', 'admin.crearproveedor', null, 'Crear proveedor'],
            ['registrar_entradas', 'admin.movimiento.crear.entrada', 'CU09', 'Registrar entrada'],
            ['registrar_salidas', 'admin.movimiento.crear.salida', 'CU10', 'Registrar salida'],
            ['registrar_ajustes', 'admin.movimiento.crear.ajuste', 'CU11', 'Registrar ajuste'],
            ['consultar_stock', 'admin.indexstock', 'CU12', 'Consultar stock actual'],
            ['consultar_stock_minimo', 'admin.stock.minimos', 'CU13', 'Consultar stock mínimo'],
            ['ver_agotados', 'admin.stock.agotados', 'CU15', 'Avisos de agotados'],
            ['ver_reportes', 'admin.indexmovimiento', null, 'Historial de movimientos'],
        ]],
        ['id' => 'pedidos', 'codigo' => 'P4', 'nombre' => 'Pedidos y Ventas', 'opciones' => [
            ['registrar_pedidos', 'admin.pedido.crear', 'CU16', 'Registrar pedido'],
            ['modificar_pedidos', 'admin.indexpedido', 'CU17', 'Modificar o cancelar pedido'],
            ['cambiar_estado_pedidos', 'admin.indexpedido', 'CU18', 'Cambiar estado del pedido'],
            ['cobrar_ventas', 'admin.indexventa', 'CU19', 'Registrar venta y pago'],
            ['cobrar_ventas', 'admin.indexmetodopago', null, 'Métodos de pago'],
            ['consultar_historial_pedidos', 'admin.indexpedido', 'CU20', 'Historial de pedidos'],
        ]],
        ['id' => 'reportes', 'codigo' => 'P5', 'nombre' => 'Reportes', 'opciones' => [
            ['ver_reportes', 'admin.reportes.diarias', 'CU21', 'Ventas diarias'],
            ['ver_reportes', 'admin.reportes.pagos', 'CU22', 'Reporte por método de pago'],
            ['ver_reportes', 'admin.reportes.inventario', 'CU23', 'Reporte de inventario'],
        ]],
        ['id' => 'promociones', 'codigo' => 'P6', 'nombre' => 'Promociones y Auditoría', 'opciones' => [
            ['gestion_promociones', 'admin.indexpromocion', 'CU24', 'Promociones y descuentos'],
            ['gestion_promociones', 'admin.crearpromocion', null, 'Crear promoción'],
            ['consultar_promociones', 'admin.promociones.consultar', null, 'Consultar promociones'],
            ['consultar_bitacora', 'admin.indexbitacora', 'CU25', 'Consultar bitácora'],
        ]],
    ];
@endphp
<nav id="menu-principal" aria-label="Menú principal">
    <a href="{{ route('home') }}" @class(['activo' => request()->routeIs('home')]) @if(request()->routeIs('home')) aria-current="page" @endif>@include('partials.icono', ['nombre' => 'home'])<span>Inicio</span></a>
    <p class="menu-etiqueta">Tu espacio de trabajo</p>
    @foreach($paquetesMenu as $paquete)
        @php
            $opciones = collect($paquete['opciones'])->filter(function ($opcion) use ($usuarioMenu) {
                if ($opcion[0] === 'consultar_promociones') {
                    return !$usuarioMenu->tienePermiso('gestion_promociones') && collect(['registrar_pedidos', 'modificar_pedidos', 'cobrar_ventas'])->contains(fn ($permiso) => $usuarioMenu->tienePermiso($permiso));
                }
                // Estas acciones requieren elegir un pedido desde el historial.
                if (in_array($opcion[2], ['CU17', 'CU18'])) {
                    return $usuarioMenu->tienePermiso($opcion[0]) && $usuarioMenu->tienePermiso('consultar_historial_pedidos');
                }
                return $opcion[0] === null || $usuarioMenu->tienePermiso($opcion[0]);
            });
            $activo = $opciones->contains(fn ($opcion) => $opcion[1] && request()->routeIs($opcion[1]));
        @endphp
        @if($opciones->isNotEmpty())
            <section class="menu-paquete">
                <button type="button" @class(['paquete-boton', 'activo' => $activo]) aria-expanded="false" aria-controls="paquete-{{ $paquete['id'] }}">
                    @include('partials.icono', ['nombre' => $paquete['id']])
                    <span>{{ $paquete['nombre'] }}</span>
                    @include('partials.icono', ['nombre' => 'chevron', 'clase' => 'paquete-flecha'])
                </button>
                <div id="paquete-{{ $paquete['id'] }}" class="paquete-opciones" hidden>
                    @foreach($opciones as [$permiso, $ruta, $cu, $texto])
                        @if($ruta === 'logout')
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="paquete-accion"><small>{{ $cu }}</small>{{ $texto }}</button></form>
                        @elseif($ruta)
                            <a href="{{ route($ruta) }}" @if(request()->routeIs($ruta)) class="activo" aria-current="page" @endif>{{ $texto }}</a>
                        @else
                            <span class="paquete-nota"><small>{{ $cu }}</small>{{ $texto }}</span>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</nav>
