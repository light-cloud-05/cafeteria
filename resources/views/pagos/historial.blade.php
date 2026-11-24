@extends('layouts.app')
@section('titulo','Historial de pagos simulados')
@include('pagos._estilos')
@section('contenido')
<h1>Historial de pagos simulados{{ $pedido ? ' · Pedido #'.($pedido->numero_diario ?? $pedido->id) : '' }}</h1>
@include('pagos._aviso')
<p>Consulta de intentos. El historial no permite editar transacciones.</p>
<div class="tarjeta tabla-contenedor"><table>
<thead><tr><th>Referencia</th><th>Pedido</th><th>Método</th><th>Importe</th><th>Estado</th><th>Inicio (Bolivia)</th><th>Resolución (Bolivia)</th><th>Origen</th></tr></thead>
<tbody>@forelse($intentos as $intento)
<tr><td><a href="{{ route('admin.pagos.show',$intento->referencia) }}"><code>{{ $intento->referencia }}</code></a></td>
<td><a href="{{ route('admin.pedido.ver',$intento->pedido_id) }}">#{{ $intento->pedido->numero_diario ?? $intento->pedido_id }}</a></td>
<td>{{ $intento->medio }}</td><td>Bs {{ number_format($intento->monto,2) }} {{ $intento->moneda }}</td>
<td>{{ $intento->estado->value }}</td><td>{{ $intento->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $intento->resuelto_en?->format('d/m/Y H:i:s') ?? 'Sin resolver' }}</td><td>SIMULACION_LOCAL</td></tr>
@empty<tr><td colspan="8">No hay intentos de pago simulados.</td></tr>@endforelse</tbody>
</table></div>{{ $intentos->links() }}
<div class="acciones"><a class="boton secundario" href="{{ route('admin.indexventa') }}">Volver a ventas y cobros</a>
@if($pedido)<a href="{{ route('admin.pedido.ver',$pedido->id) }}">Detalle del pedido</a>@endif</div>
@endsection
