@extends('layouts.app')
@section('titulo', 'Inicio')
@section('contenido')
<section class="hero-inicio">
    <div class="hero-texto">
        <span class="hero-etiqueta">Tu día, en equilibrio</span>
        <h1>Hola, {{ explode(' ', trim(auth()->user()->nombreCompleto))[0] }}<span class="marca-punto">.</span></h1>
        <p>Todo listo para un gran día en KARMA.<br>Gestiona tu cafetería, un buen momento a la vez.</p>
        @if(auth()->user()->tienePermiso('registrar_pedidos'))
            <a class="boton" href="{{ route('admin.pedido.crear') }}">Nuevo pedido @include('partials.icono', ['nombre' => 'arrow'])</a>
        @elseif(auth()->user()->tienePermiso('cobrar_ventas'))
            <a class="boton" href="{{ route('admin.indexventa') }}">Ir a ventas @include('partials.icono', ['nombre' => 'arrow'])</a>
        @elseif(auth()->user()->tienePermiso('gestion_productos'))
            <a class="boton" href="{{ route('admin.indexproducto') }}">Gestionar productos @include('partials.icono', ['nombre' => 'arrow'])</a>
        @endif
    </div>
    <div class="hero-marca"><img src="{{ asset('images/karma.png') }}" alt="Cafetería Karma"><span>Hecho con pasión. Servido con calma.</span></div>
</section>
@if(count($metricas))
    <div class="titulo-seccion"><h2>Tu cafetería hoy</h2><span>Un vistazo a lo que importa</span></div>
    <div class="metricas-inicio">
        @foreach($metricas as $metrica)
            <a class="metrica-card" href="{{ route($metrica['ruta']) }}">
                <div class="metrica-cabecera"><span>{{ $metrica['titulo'] }}</span><span class="metrica-icono">@include('partials.icono', ['nombre' => $metrica['icono']])</span></div>
                <strong>{{ $metrica['valor'] }}</strong><small>{{ $metrica['detalle'] }}</small>
            </a>
        @endforeach
    </div>
@endif
<div class="titulo-seccion"><h2>A un clic</h2><span>Continúa con tu día</span></div>
<div class="accesos-inicio">
    @foreach([
        ['gestion_usuarios', 'admin.indexusuario', 'usuarios', 'Tu equipo', 'Organiza usuarios, accesos y responsabilidades.'],
        ['buscar_productos', 'admin.catalogo', 'productos', 'El catálogo', 'Encuentra el producto para cada momento.'],
        ['consultar_stock', 'admin.indexstock', 'inventario', 'Tu inventario', 'Consulta las existencias de tu cafetería.'],
        ['consultar_historial_pedidos', 'admin.indexpedido', 'pedidos', 'Los pedidos', 'Revisa el historial y sigue cada pedido.'],
        ['gestion_promociones', 'admin.indexpromocion', 'promociones', 'Algo especial', 'Gestiona promociones y descuentos.'],
    ] as [$permiso, $ruta, $icono, $titulo, $descripcion])
        @if(auth()->user()->tienePermiso($permiso))
            <a class="acceso-card" href="{{ route($ruta) }}"><span class="acceso-icono">@include('partials.icono', ['nombre' => $icono])</span><div><strong>{{ $titulo }}</strong><p>{{ $descripcion }}</p></div>@include('partials.icono', ['nombre' => 'arrow'])</a>
        @endif
    @endforeach
    <a class="acceso-card" href="{{ route('perfil.contrasena') }}"><span class="acceso-icono">@include('partials.icono', ['nombre' => 'lock'])</span><div><strong>Mi cuenta</strong><p>Actualiza tu contraseña de acceso.</p></div>@include('partials.icono', ['nombre' => 'arrow'])</a>
</div>
@endsection
