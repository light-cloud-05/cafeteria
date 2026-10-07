@extends('layouts.app')
@section('titulo','Cobrar pedido #'.$pedido->id)
@section('contenido')
<h1>Cobrar pedido #{{ $pedido->id }}</h1><p>Total: <strong>Bs {{ number_format($pedido->total,2) }}</strong></p>
<a class="boton secundario" href="{{ route('admin.venta.siguiente',$pedido->id) }}">Siguiente pedido</a>
<div class="tarjeta tabla-contenedor"><table><thead><tr><th>Producto</th><th>Cantidad solicitada</th><th>Stock actual</th><th>Subtotal</th></tr></thead><tbody>
@foreach($pedido->detalles as $detalle)<tr><td>{{ $detalle->producto?->nombre }}</td><td>{{ $detalle->cantidad }}</td><td>{{ $detalle->producto?->stock?->stock_actual ?? 0 }}</td><td>Bs {{ number_format($detalle->subtotal,2) }}</td></tr>@endforeach
</tbody></table></div>
@if($conflictos->isNotEmpty())<div class="aviso error" role="alert">El pedido tiene productos sin disponibilidad suficiente. Acuerda una alternativa con el cliente y corrige el pedido antes de cobrar.</div>@endif
<p><a class="boton secundario" href="{{ route('admin.venta.pedido.editar',$pedido->id) }}">Corregir pedido / aplicar promoción</a></p>
@if($errors->any())<div class="aviso error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ route('admin.venta.store',$pedido->id) }}" class="tarjeta formulario">@csrf
<input type="hidden" name="version_pedido" value="{{ $pedido->versionCobro() }}">
<div class="campo"><label for="metodo">Método de pago</label><select id="metodo" name="metodo_pago" required><option @selected(old('metodo_pago')==='Efectivo')>Efectivo</option><option @selected(old('metodo_pago')==='QR')>QR</option></select></div>
<div class="campo" id="efectivo"><label for="recibido">Monto recibido (Bs)</label><input id="recibido" name="monto_recibido" type="number" min="0" max="999999.99" step="0.01" value="{{ old('monto_recibido') }}"></div>
<p id="cambio" role="status"></p><p>Para QR, confirma que recibiste el pago antes de registrar. El sistema no procesa la transacción bancaria.</p><button class="boton" @disabled($conflictos->isNotEmpty())>Registrar pago</button><a class="boton secundario" href="{{ route('admin.indexventa') }}">Volver</a></form>
@if(session('borrador_guardado'))<script>try{localStorage.removeItem(@json(session('borrador_guardado')));}catch(e){}</script>@endif
<script>
const metodo=document.getElementById('metodo'),recibido=document.getElementById('recibido');function calcularCambio(){const efectivo=metodo.value==='Efectivo';document.getElementById('efectivo').hidden=!efectivo;recibido.required=efectivo;recibido.disabled=!efectivo;const cambio=Number(recibido.value)-Number(@json($pedido->total));document.getElementById('cambio').textContent=efectivo ? (cambio<0 ? 'Monto insuficiente' : 'Cambio estimado: Bs '+cambio.toFixed(2)) : 'Cambio: Bs 0.00';}metodo.addEventListener('change',calcularCambio);recibido.addEventListener('input',calcularCambio);calcularCambio();
</script>
@endsection

