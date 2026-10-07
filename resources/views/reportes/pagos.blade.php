@extends('layouts.app')
@section('titulo','Reporte por método de pago')
@section('contenido')
<h1>Reporte por método de pago</h1><form method="GET" class="tarjeta formulario"><div class="campo"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="{{ $desde }}" required></div><div class="campo"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="{{ $hasta }}" required></div><button class="boton">Consultar</button></form>
@if($errors->any())<div class="aviso error">{{ $errors->first() }}</div>@endif
<div class="tarjeta tabla-contenedor"><table><thead><tr><th>Método de pago</th><th>Ventas</th><th>Total</th></tr></thead><tbody>@forelse($resumen as $metodo=>$datos)<tr><td>{{ $metodo }}</td><td>{{ $datos['cantidad'] }}</td><td>Bs {{ number_format($datos['total'],2) }}</td></tr>@empty<tr><td colspan="3">Sin ventas registradas.</td></tr>@endforelse</tbody></table><p>Total: Bs {{ number_format($resumen->sum('total'),2) }}</p></div>
@endsection
