@extends('layouts.app')
@section('titulo','Movimientos de inventario')
@section('contenido')
<h1>Movimientos de inventario</h1><div class="tarjeta tabla-contenedor"><table><thead><tr><th>Fecha</th><th>Tipo</th><th>Producto</th><th>Cantidad / diferencia</th><th>Motivo</th><th>Proveedor</th><th>Usuario</th></tr></thead><tbody>
@forelse($movimientos as $m)<tr><td>{{ $m->fecha_hora }}</td><td>{{ $m->tipo }}</td><td>{{ $m->producto?->nombre }}</td><td>{{ $m->cantidad }}</td><td>{{ $m->motivo }}</td><td>{{ $m->proveedor?->nombre ?? 'Sin proveedor' }}</td><td>{{ $m->usuario?->nombreCompleto }}</td></tr>@empty<tr><td colspan="7">Sin movimientos.</td></tr>@endforelse
</tbody></table></div>{{ $movimientos->links() }}
@endsection
