@extends('layouts.app')
@section('titulo','Pedido #'.$pedido->id)
@section('contenido')
<h1>Pedido #{{ $pedido->id }}</h1><p>{{ $pedido->fechaHora }} — {{ $pedido->estado }} — {{ $pedido->usuario?->nombreCompleto }}</p>
@if($errors->any())<div class="aviso error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="tarjeta tabla-contenedor"><table><thead><tr><th>Producto</th><th>Cantidad</th><th>Opción</th><th>Precio unitario</th><th>Subtotal</th></tr></thead><tbody>@foreach($pedido->detalles as $d)<tr><td>{{ $d->producto?->nombre }}</td><td>{{ $d->cantidad }}</td><td>{{ $d->opcion }}</td><td>{{ number_format($d->precioUnitario,2) }}</td><td>{{ number_format($d->subtotal,2) }}</td></tr>@endforeach</tbody></table>
<p>Subtotal: Bs {{ number_format($pedido->subtotal,2) }} | Descuento: Bs {{ number_format($pedido->descuento,2) }} {{ $pedido->promocion ? '('.$pedido->promocion->nombre.')' : '' }}</p><p><strong>Total: Bs {{ number_format($pedido->total,2) }}</strong></p>
@if($pedido->venta)<p>Pago: {{ $pedido->venta->metodo_pago }} — Recibido: Bs {{ number_format($pedido->venta->monto_recibido,2) }} — Cambio: Bs {{ number_format($pedido->venta->cambio,2) }}</p>@endif</div>
<div class="acciones">
@if(auth()->user()->tienePermiso('cobrar_ventas'))<a class="boton" href="{{ route('admin.venta.siguiente',$pedido->id) }}">Siguiente pedido</a>@endif
@if($pedido->estado === 'Registrado' && !$pedido->venta)
@if(auth()->user()->tienePermiso('modificar_pedidos'))<a class="boton" href="{{ route('admin.pedido.editar',$pedido->id) }}">Modificar pedido</a><form method="POST" action="{{ route('admin.pedido.cancelar',$pedido->id) }}" onsubmit="return confirm('¿Cancelar este pedido?');">@csrf<button class="boton secundario">Cancelar pedido</button></form>@endif
@if(auth()->user()->tienePermiso('cobrar_ventas'))<a class="boton" href="{{ route('admin.venta.crear',$pedido->id) }}">Cobrar pedido</a><a class="boton secundario" href="{{ route('admin.venta.pedido.editar',$pedido->id) }}">Corregir pedido en caja</a>@endif
@endif
@if($pedido->estado === 'Pagado' && auth()->user()->tienePermiso('cambiar_estado_pedidos'))<form method="POST" action="{{ route('admin.pedido.estado',$pedido->id) }}">@csrf @method('PUT')<input type="hidden" name="estado" value="Completado"><button class="boton">Marcar como completado</button></form>@endif
@if(auth()->user()->tienePermiso('registrar_pedidos'))<a class="boton secundario" href="{{ route('admin.pedido.crear') }}">Nuevo pedido</a>@endif
@if(auth()->user()->tienePermiso('consultar_historial_pedidos'))<a class="boton secundario" href="{{ route('admin.indexpedido') }}">Historial</a>@endif
</div>
@if(session('borrador_guardado'))<script>try{localStorage.removeItem(@json(session('borrador_guardado')));}catch(e){}</script>@endif
@endsection
