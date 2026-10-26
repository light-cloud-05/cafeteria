@extends('layouts.app')
@section('titulo', 'Rectificación de cierre diario')
@section('contenido')
<div class="caja-panel"><h1>Rectificación de cierre diario #{{ $cierre->id }}</h1>
@include('caja._navegacion')
@include('caja._aviso')
<p>Fecha operativa: {{ $cierre->fecha_operativa->format('d/m/Y') }}. Versión esperada: {{ $version->numero_version }}.</p>
<p>Se conservarán las versiones e instantáneas anteriores. Solo puedes cambiar el efectivo contado y la observación.</p>
<form method="POST" action="{{ route('admin.caja.rectificar', $cierre->id) }}" class="tarjeta formulario">
@csrf
<input type="hidden" name="version_esperada" value="{{ $version->numero_version }}">
<fieldset @disabled(!$habilitada)><legend>Nuevo recuento</legend>
@include('caja._campos-conteo', ['contado' => $version->efectivo_contado, 'observacion' => $version->observacion ?? ''])
<div class="campo"><label for="motivo">Motivo de rectificación obligatorio</label><textarea id="motivo" name="motivo" maxlength="1000" rows="3" required></textarea></div>
<button class="boton" type="submit">Registrar nueva versión</button></fieldset>
</form></div>
@endsection
