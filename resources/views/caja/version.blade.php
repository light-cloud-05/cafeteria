@extends('layouts.app')
@section('titulo', 'Versión de caja #'.$cierre->id)
@section('contenido')
<div class="caja-panel">
<h1>Caja #{{ $cierre->id }} — versión {{ $version->numero_version }}</h1>
@include('caja._navegacion')
@include('caja._aviso')
<p>Fecha operativa: {{ $cierre->fecha_operativa?->format('d/m/Y') ?? 'Histórico no oficial' }}</p>
@include('caja._version')
<a class="boton secundario" href="{{ route('admin.caja.show', $cierre->id) }}">Ver cierre vigente e historial</a>
</div>
@endsection
