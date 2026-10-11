@extends('layouts.app')
@section('titulo','Ventas y cobros')
@section('contenido')
<h1>Ventas y cobros</h1>
@if($errors->any())<div class="aviso error">{{ $errors->first() }}</div>@endif
<h2>Pedidos pendientes de cobro</h2><div class="tarjeta tabla-contenedor"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Total</th><th>Acción</th></tr></thead><tbody>@forelse($pedidos as $p)<tr><td>#{{ $p->numero_diario ?? $p->id }}</td><td>{{ $p->fechaHora }}</td><td>Bs {{ number_format($p->total,2) }}</td><td><a class="boton" href="{{ route('admin.venta.crear',$p->id) }}">Cobrar</a> <a href="{{ route('admin.pedido.ver',$p->id) }}">Detalle</a></td></tr>@empty<tr><td colspan="4">No hay pedidos pendientes de cobro.</td></tr>@endforelse</tbody></table></div>{{ $pedidos->links() }}
<h2>Últimas ventas</h2><div class="tarjeta tabla-contenedor"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Método</th><th>Total</th><th>Cambio</th></tr></thead><tbody>@forelse($ventas as $v)<tr><td><a href="{{ route('admin.pedido.ver',$v->pedido_id) }}">#{{ $v->pedido?->numero_diario ?? $v->pedido_id }}</a></td><td>{{ $v->fecha_hora }}</td><td>{{ $v->metodo_pago }}</td><td>{{ number_format($v->total,2) }}</td><td>{{ number_format($v->cambio,2) }}</td></tr>@empty<tr><td colspan="5">Sin ventas registradas.</td></tr>@endforelse</tbody></table></div>
@endsection
