@extends('layouts.app')
@section('titulo', 'Previsualización de caja')
@section('contenido')
<div class="caja-panel">
<h1>Previsualización de caja</h1>
@include('caja._navegacion')
@include('caja._aviso')
@if($errors->any())<div class="aviso error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="GET" action="{{ route('admin.caja.previsualizacion') }}" class="tarjeta formulario caja-filtros"><div class="campo"><label for="fecha">Fecha operativa de Bolivia</label><input id="fecha" name="fecha" type="date" value="{{ $fecha }}" required></div><div class="acciones"><button class="boton">Consultar</button></div></form>
<p>Consulta provisional del {{ $fecha }}. {{ $abierto ? 'Día operativo abierto: los importes pueden cambiar.' : 'Día operativo terminado.' }}</p>
@if($cierre)<p>Ya existe un cierre oficial. Esta consulta usa ventas actuales; para consultar el registro conservado, <a href="{{ route('admin.caja.show', $cierre->id) }}">ver el cierre y sus instantáneas</a>.</p>@endif
@if($errorFinanciero)<div class="aviso error" role="alert">{{ $errorFinanciero }}</div>
@elseif($resumen)
<div class="tarjeta"><dl class="caja-datos"><div><dt>Cantidad de ventas completadas</dt><dd>{{ $resumen['cantidad_ventas'] }}</dd></div>
@foreach(['total_ventas'=>'Total vendido','efectivo_esperado'=>'Efectivo esperado','qr_registrado'=>'QR registrado','tarjeta_registrado'=>'Tarjeta registrada'] as $campo=>$titulo)<div><dt>{{ $titulo }}</dt><dd class="caja-importe">{{ \App\Support\ImporteCajaVista::formatear($resumen[$campo]) }}</dd></div>@endforeach
</dl>@if($resumen['cantidad_ventas'] === 0)<p>Sin ventas completadas para esta fecha.</p>@endif</div>
@endif
<a class="boton secundario" href="{{ route('admin.caja.index') }}">Volver a caja</a>
</div>
@endsection
