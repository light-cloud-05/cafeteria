@extends('layouts.app')
@section('titulo','Registrar '.$tipo)
@section('contenido')
<h1>Registrar {{ $tipo }} de inventario</h1>
@if($errors->any())<div class="aviso error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form class="tarjeta formulario" method="POST" action="{{ route('admin.movimiento.store.'.$tipo) }}" data-borrador="inventario-{{ $tipo }}-{{ auth()->id() }}" data-guardado="{{ session('borrador_guardado') ? '1' : '0' }}">
@csrf
<input type="hidden" data-errores="{{ $errors->any() ? '1' : '0' }}">
<div class="campo"><label>Producto</label><select name="producto_id" required><option value="">Seleccionar</option>@foreach($productos as $producto)<option value="{{ $producto->id }}" data-stock="{{ $producto->stock?->stock_actual ?? 0 }}" @selected(old('producto_id') == $producto->id)>{{ $producto->nombre }} — stock {{ $producto->stock?->stock_actual ?? 0 }}</option>@endforeach</select></div>
<div class="campo"><label>{{ $tipo === 'ajuste' ? 'Cantidad real contada' : 'Cantidad' }}</label><input name="cantidad" type="number" step="1" min="{{ $tipo === 'ajuste' ? '0' : '0.01' }}" max="99999999" value="{{ old('cantidad') }}" required></div>
@if($tipo === 'ajuste')<p id="diferencia" role="status"></p>@endif
@if($tipo === 'entrada')<div class="campo"><label>Proveedor (opcional)</label><select name="proveedor_id"><option value="">Sin proveedor</option>@foreach($proveedores as $proveedor)<option value="{{ $proveedor->id }}" @selected(old('proveedor_id') == $proveedor->id)>{{ $proveedor->nombre }}</option>@endforeach</select></div>@endif
<div class="campo"><label>Motivo</label><input name="motivo" maxlength="255" value="{{ old('motivo') }}" required></div>
<div class="campo"><label>Fecha y hora</label><input type="datetime-local" name="fecha_hora" value="{{ old('fecha_hora',now()->format('Y-m-d\TH:i')) }}" required></div>
<button class="boton">Registrar {{ $tipo }}</button>
<button class="boton secundario" type="button" data-descartar>Descartar borrador</button>
<p>El borrador se conserva en este navegador hasta guardar o descartarlo.</p>
</form>
@if($tipo === 'ajuste')<script>
const f=document.querySelector('[data-borrador]');function diferencia(){const s=Number(f.producto_id.selectedOptions[0]?.dataset.stock||0);document.getElementById('diferencia').textContent='Diferencia: '+(Number(f.cantidad.value)-s).toFixed(2);}f.addEventListener('input',diferencia);window.addEventListener('load',diferencia);
</script>@endif
@endsection
