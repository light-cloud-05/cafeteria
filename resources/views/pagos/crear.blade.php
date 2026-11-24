@extends('layouts.app')
@section('titulo','Preparar pago de demostración')
@include('pagos._estilos')
@section('contenido')
<div class="pago-demo-contenedor pago-checkout">
<header class="pago-checkout-cabecera"><div><p class="pago-demo-ceja">KARMA <span>/</span> CHECKOUT ACADÉMICO</p>
<h1>{{ $borrador['medio']==='QR_SIMULADO' ? 'QR simulado' : 'Tarjeta simulada' }}</h1>
<p class="pago-subtitulo">Confirma el importe para comenzar la demostración.</p></div><span class="pago-entorno">@include('pagos._icono',['icono'=>'escudo']) Entorno de prueba</span></header>
@include('pagos._aviso')
@if($errors->any())<div class="aviso error" role="alert">{{ $errors->first() }}</div>@endif
<section class="tarjeta pago-panel pago-confirmacion">
    <div class="pago-panel-titulo"><span class="pago-icono-fondo">@include('pagos._icono',['icono'=>'bolsa'])</span><h2>Confirma el pedido</h2></div>
    <p>Pedido <strong>#{{ $pedido->numero_diario ?? $pedido->id }}</strong></p>
    <p class="pago-demo-importe">Bs {{ number_format($pedido->total,2) }} <small>BOB</small></p>
    <div class="pago-metodo-seleccionado"><div class="pago-metodo-simbolo">@include('pagos._icono',['icono'=>$borrador['medio']==='QR_SIMULADO' ? 'qr' : 'tarjeta'])</div><div><strong>{{ $borrador['medio']==='QR_SIMULADO' ? 'QR de demostración' : 'Tarjeta de demostración' }}</strong><small>No requiere datos bancarios</small></div><span class="pago-seleccion-check">@include('pagos._icono',['icono'=>'check'])</span></div>
    <p>Al continuar se crea un intento pendiente. Todavía no se registra una venta.</p>
    <form method="POST" action="{{ route('admin.pagos.store',$pedido->id) }}" data-pago-inicio>
        @csrf
        <input type="hidden" name="medio" value="{{ $borrador['medio'] }}">
        <input type="hidden" name="version_pedido" value="{{ $borrador['version_pedido'] }}">
        <input type="hidden" name="clave_idempotencia" value="{{ $borrador['clave_idempotencia'] }}">
        <button class="boton pago-boton-principal" type="submit">Crear intento de demostración @include('pagos._icono',['icono'=>'flecha'])</button>
    </form>
    <p><a href="{{ route('admin.pagos.recuperar',['clave_idempotencia'=>$borrador['clave_idempotencia']]) }}">Consultar esta solicitud si ya la enviaste</a></p>
    <p>Si se interrumpe la conexión, consulta la misma solicitud antes de volver a cobrar. No se crean reintentos automáticos.</p>
</section>
<div class="pago-navegacion"><a class="boton secundario" href="{{ route('admin.venta.crear',$pedido->id) }}">Volver al cobro</a></div>
</div>
<script src="{{ asset('js-pagos-demo.js') }}" defer></script>
@endsection
