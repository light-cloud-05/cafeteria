@extends('layouts.app')
@section('titulo','Ventas diarias')
@section('contenido')
<h1>Reporte de ventas diarias</h1><form method="GET" class="tarjeta formulario"><div class="campo"><label for="fecha">Fecha</label><input id="fecha" type="date" name="fecha" value="{{ $fecha }}" required></div><button class="boton">Consultar</button></form>
@if($errors->any())<div class="aviso error">{{ $errors->first() }}</div>@endif
<p>Total: <strong>Bs {{ number_format($total,2) }}</strong> — Pedidos cobrados: {{ $cantidad }}</p><div class="tarjeta tabla-contenedor"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Método</th><th>Total</th></tr></thead><tbody>@forelse($ventas as $v)<tr><td>{{ $v->pedido_id }}</td><td>{{ $v->fecha_hora }}</td><td>{{ $v->metodo_pago }}</td><td>{{ number_format($v->total,2) }}</td></tr>@empty<tr><td colspan="4">Sin ventas registradas.</td></tr>@endforelse</tbody></table></div>
@endsection
