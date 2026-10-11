@extends('layouts.app')
@section('titulo','Promociones')
@section('contenido')
@php($puedeGestionar = auth()->user()->tienePermiso('gestion_promociones'))
<div class="barra"><h1>Promociones y descuentos</h1>@if($puedeGestionar)<a class="boton" href="{{ route('admin.crearpromocion') }}">Nueva promoción</a>@endif</div>
<p>Hora de Bolivia: {{ now()->format('d/m/Y H:i') }}. Una promoción se puede aplicar cuando está <strong>Activa y vigente</strong>.</p>
@if(!$puedeGestionar)<p>El mesero la selecciona en Nuevo pedido. El cajero puede aplicarla en Corregir pedido, antes de cobrar.</p>@endif
<div class="tarjeta tabla-contenedor"><table><thead><tr><th>Nombre</th><th>Tipo / descuento</th><th>Aplica a</th><th>Fechas</th><th>Horario de Bolivia</th><th>Estado</th>@if($puedeGestionar)<th>Acciones</th>@endif</tr></thead><tbody>
@forelse($promociones as $p)<tr><td>{{ $p->nombre }}</td><td>{{ $p->tipo }}: {{ $p->valor }}@if($p->topeDescuento !== null)<br>Tope: Bs {{ $p->topeDescuento }}@endif</td><td>@if($p->aplicaSobre==='Pedido')Todos los productos
@else @foreach($productos->whereIn('id',$p->productosAplicables()) as $producto)<div>{{ $producto->nombre }} — Bs {{ number_format($producto->precio,2) }}</div>@endforeach @endif</td><td>{{ $p->fechaInicio }} al {{ $p->fechaFin }}</td><td>{{ $p->horaInicio ? substr($p->horaInicio,0,5).' a '.substr($p->horaFin,0,5) : 'Todo el día' }}</td><td><span class="etiqueta {{ $p->vigente() ? 'activa' : 'inactiva' }}">{{ $p->estadoAplicacion() }}</span></td>
@if($puedeGestionar)<td><div class="acciones"><a class="boton secundario" href="{{ route('admin.editpromocion',$p->id) }}">Editar</a><form method="POST" action="{{ $p->activo ? route('admin.destroypromocion',$p->id) : route('admin.promocion.activar',$p->id) }}">@csrf @if($p->activo)@method('DELETE')@endif<button class="boton secundario">{{ $p->activo ? 'Desactivar' : 'Activar' }}</button></form></div></td>@endif</tr>
@empty<tr><td colspan="{{ $puedeGestionar ? 7 : 6 }}">No hay promociones registradas.</td></tr>@endforelse</tbody></table></div>
@endsection
