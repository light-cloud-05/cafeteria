@extends('layouts.app')
@section('titulo', 'Detalle de caja #'.$cierre->id)
@section('contenido')
<div class="caja-panel">
<h1>Detalle de caja #{{ $cierre->id }}</h1>
@include('caja._navegacion')
@include('caja._aviso')
<p>Fecha operativa: {{ $cierre->fecha_operativa?->format('d/m/Y') ?? 'Sin fecha operativa: histórico no oficial' }}</p>
<p><span class="etiqueta {{ $cierre->fecha_operativa ? 'activa' : 'inactiva' }}">{{ $cierre->fecha_operativa ? 'Cierre oficial' : 'Histórico no oficial' }}</span></p>
@if(!$cierre->fecha_operativa)<div class="tarjeta"><h2>Registro histórico no oficial</h2><p>No se ha convertido en un cierre diario oficial.</p><dl><dt>Total del sistema registrado</dt><dd>{{ \App\Support\ImporteCajaVista::formatear($cierre->totalSistema) }}</dd><dt>Total declarado registrado</dt><dd>{{ \App\Support\ImporteCajaVista::formatear($cierre->totalDeclarado) }}</dd><dt>Diferencia registrada</dt><dd>{{ \App\Support\ImporteCajaVista::formatear($cierre->diferencia) }}</dd><dt>Observación</dt><dd>{{ $cierre->observacion ?? 'Sin observación' }}</dd></dl></div>@endif
@if($version)@include('caja._version')@else<p>No hay versiones financieras registradas. No se reconstruyen importes a partir de ventas actuales.</p>@endif
@include('caja._historial')
<a class="boton secundario" href="{{ route('admin.caja.index') }}">Volver a caja</a>
</div>
@endsection
