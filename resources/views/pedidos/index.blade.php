@extends('layouts.app')
@section('titulo','Historial de pedidos')
@section('contenido')
<h1>Historial de pedidos</h1>
<form method="GET" class="tarjeta formulario"><div class="campo"><label for="fecha">Fecha</label><input id="fecha" name="fecha" type="date" value="{{ request('fecha') }}"></div><div class="campo"><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option>@foreach(['Registrado','Pagado','Completado','Cancelado'] as $estado)<option @selected(request('estado')===$estado)>{{ $estado }}</option>@endforeach</select></div><button class="boton">Filtrar</button><a class="boton secundario" href="{{ route('admin.indexpedido') }}">Limpiar</a></form>
@if($errors->any())<div class="aviso error">{{ $errors->first() }}</div>@endif
<div class="tarjeta tabla-contenedor"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Usuario</th><th>Total</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>@forelse($pedidos as $p)<tr><td>#{{ $p->id }}</td><td>{{ $p->fechaHora }}</td><td>{{ $p->usuario?->nombreCompleto }}</td><td>Bs {{ number_format($p->total,2) }}</td><td>{{ $p->estado }}</td><td><a href="{{ route('admin.pedido.ver',$p->id) }}">Ver pedido</a></td></tr>@empty<tr><td colspan="6">No se encontraron pedidos.</td></tr>@endforelse</tbody></table></div>{{ $pedidos->links() }}
@endsection
