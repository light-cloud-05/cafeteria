@extends('layouts.app')
@section('titulo','Buscar producto')
@section('contenido')
<h1>Buscar producto</h1><form method="GET"><div class="campo"><label>Nombre o código (ID)</label><input name="q" maxlength="255" value="{{ $q }}"></div><button class="boton">Buscar</button></form>
<div class="tarjeta tabla-contenedor"><table><thead><tr><th>Código</th><th>Producto</th><th>Descripción</th><th>Precio</th><th>Disponibilidad</th><th>Seleccionar</th></tr></thead><tbody>
@forelse($productos as $p)<tr><td>{{ $p->id }}</td><td>{{ $p->nombre }}</td><td>{{ $p->descripcion }}</td><td>Bs {{ $p->precio }}</td><td>{{ $p->vendible() ? 'Disponible' : 'Agotado o no disponible' }}</td><td><a href="{{ route('admin.catalogo',['producto'=>$p->id]) }}">Ver producto</a></td></tr>@empty<tr><td colspan="6">Producto no encontrado.</td></tr>@endforelse
</tbody></table></div>
@endsection
