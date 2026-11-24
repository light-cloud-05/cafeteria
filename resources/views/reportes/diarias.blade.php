@extends('layouts.app')
@section('titulo','Ventas diarias')
@section('contenido')
<h1>Reporte de ventas diarias</h1><form method="GET" class="tarjeta formulario"><div class="campo"><label for="fecha">Fecha</label><input id="fecha" type="date" name="fecha" value="{{ $fecha }}" required></div><button class="boton">Consultar</button></form>
@if($errors->any())<div class="aviso error">{{ $errors->first() }}</div>@endif
@include('reportes._contexto')
<p>Total: <strong>{{ \App\Support\ImporteReporteVista::formatear($total) }}</strong> — Pedidos cobrados: {{ $cantidad }}</p>
@include('reportes._ventas')
@endsection
