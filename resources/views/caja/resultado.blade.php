@extends('layouts.app')
@section('titulo', 'Resultado de solicitud de caja')
@section('contenido')
<div class="caja-panel"><h1>Resultado de solicitud de caja</h1>
@include('caja._navegacion')
@include('caja._aviso')
<div class="aviso error" role="alert"><p>{{ $mensaje }}</p>@if($incierto)<strong>No reenvíes la solicitud automáticamente: la operación podría haberse registrado.</strong>@endif
@foreach($errores ?? [] as $campo=>$error)<p>{{ $campo }}: {{ $error }}</p>@endforeach</div>
<a class="boton secundario" href="{{ route('admin.caja.index') }}">Consultar cierres e historial</a>
</div>
@endsection
