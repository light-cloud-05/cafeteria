@extends('layouts.app')
@section('titulo', 'Confirmación de cierre diario')
@section('contenido')
<div class="caja-panel"><h1>Confirmación de cierre diario</h1>
@include('caja._navegacion')
@include('caja._aviso')
<form method="GET" action="{{ route('admin.caja.confirmacion') }}" class="tarjeta formulario caja-filtros">
<div class="campo"><label for="fecha-operativa">Día operativo terminado en Bolivia</label><input id="fecha-operativa" name="fecha" type="date" value="{{ $fecha }}" required></div>
<div class="acciones"><button class="boton secundario" type="submit">Consultar fecha</button></div>
</form>
<p>Fecha operativa de Bolivia: {{ $fecha }}. El responsable se obtiene de tu sesión. Los importes se recalculan al confirmar.</p>
<div class="tarjeta"><dl class="caja-datos">@foreach(['total_ventas'=>'Total vendido','efectivo_esperado'=>'Efectivo esperado','qr_registrado'=>'QR registrado'] as $campo=>$etiqueta)<div><dt>{{ $etiqueta }}</dt><dd class="caja-importe">{{ \App\Support\ImporteCajaVista::formatear($resumen[$campo]) }}</dd></div>@endforeach</dl></div>
<form method="POST" action="{{ route('admin.caja.confirmar') }}" class="tarjeta formulario">
@csrf
<input type="hidden" name="fecha" value="{{ $fecha }}">
<fieldset @disabled(!$habilitada)><legend>Efectivo contado del día</legend>
@include('caja._campos-conteo', ['contado' => '', 'observacion' => ''])
<p>Si hay faltante o sobrante, debes explicarlo en la observación. No se enviarán totales ni diferencias.</p>
<button class="boton" type="submit">Confirmar cierre diario</button></fieldset>
</form></div>
@endsection
