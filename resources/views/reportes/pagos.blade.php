@extends('layouts.app')
@section('titulo','Reporte por método de pago')
@section('contenido')
<h1>Reporte por método de pago</h1><form method="GET" class="tarjeta formulario"><div class="campo"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="{{ $desde }}" required></div><div class="campo"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="{{ $hasta }}" required></div><button class="boton">Consultar</button></form>
@if($errors->any())<div class="aviso error">{{ $errors->first() }}</div>@endif
@include('reportes._contexto')
<h2>Resumen por método de pago</h2>
<p>Cada venta se cuenta una sola vez en su método. Mixto muestra los componentes sin duplicar su importe.</p>
<div class="tarjeta tabla-contenedor" role="region" aria-label="Resumen por método" tabindex="0"><table><thead><tr><th>Método de pago</th><th>Ventas</th><th>Efectivo</th><th>QR</th><th>Tarjeta</th><th>Total</th><th>Origen</th></tr></thead><tbody>
@forelse($resumen as $metodo=>$datos)<tr><td>{{ match($metodo) {'QR'=>'QR manual','QR_SIMULADO'=>'QR simulado','TARJETA_SIMULADA'=>'Tarjeta simulada',default=>$metodo} }}</td><td>{{ $datos['cantidad'] }}</td>
@foreach(['monto_efectivo','monto_qr','monto_tarjeta','total'] as $campo)<td>{{ \App\Support\ImporteReporteVista::formatear($datos[$campo]) }}</td>@endforeach
<td>{{ $datos['origen'] === 'SIMULACION_LOCAL' ? 'SIMULACION_LOCAL' : 'Presencial' }}</td></tr>
@empty<tr><td colspan="7">Sin ventas registradas.</td></tr>@endforelse
</tbody></table></div>
<h2>Detalle de ventas</h2>
@include('reportes._ventas')
@endsection
