@extends('layouts.app')
@section('titulo',$promocion->exists ? 'Editar promoción' : 'Nueva promoción')
@section('contenido')
<h1>{{ $promocion->exists ? 'Editar promoción' : 'Nueva promoción' }}</h1>
@if($errors->any())<div class="aviso error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form class="tarjeta formulario" method="POST" action="{{ $promocion->exists ? route('admin.updatepromocion',$promocion->id) : route('admin.storedpromocion') }}">@csrf
<div class="campo"><label for="nombre">Nombre</label><input id="nombre" name="nombre" maxlength="100" value="{{ old('nombre',$promocion->nombre) }}" required></div>
<div class="campo"><label for="tipo">Tipo</label><select id="tipo" name="tipo" required>@foreach(['Porcentaje','Monto fijo'] as $tipo)<option @selected(old('tipo',$promocion->tipo)===$tipo)>{{ $tipo }}</option>@endforeach</select></div>
<div class="campo"><label for="aplica">Aplica sobre</label><select id="aplica" name="aplicaSobre" required>@foreach(['Pedido','Producto'] as $aplica)<option value="{{ $aplica }}" @selected(old('aplicaSobre',$promocion->aplicaSobre)===$aplica)>{{ $aplica === 'Pedido' ? 'Todos los productos (pedido completo)' : 'Seleccionar uno o varios productos' }}</option>@endforeach</select></div>
<fieldset class="campo" id="producto"><legend>Productos (marca uno o varios)</legend><div class="productos-promocion">@foreach($productos as $p)<label class="opcion-permiso"><input type="checkbox" name="producto_ids[]" value="{{ $p->id }}" @checked(in_array($p->id,old('producto_ids',$promocion->productosAplicables())))><span>{{ $p->nombre }} — Bs {{ number_format($p->precio,2) }} — stock {{ $p->stock?->stock_actual ?? 0 }}</span></label>@endforeach</div><p id="seleccionados" role="status"></p></fieldset>
<div class="campo"><label for="valor">Valor (% o Bs)</label><input id="valor" name="valor" type="number" min="0.01" step="0.01" max="999999.99" value="{{ old('valor',$promocion->valor) }}" required></div>
<div class="campo"><label for="tope">Tope de descuento (Bs, opcional)</label><input id="tope" name="topeDescuento" type="number" min="0" step="0.01" max="999999.99" value="{{ old('topeDescuento',$promocion->topeDescuento) }}"></div>
<div class="campo"><label for="inicio">Fecha inicial</label><input id="inicio" name="fechaInicio" type="date" value="{{ old('fechaInicio',$promocion->fechaInicio) }}" required></div><div class="campo"><label for="fin">Fecha final</label><input id="fin" name="fechaFin" type="date" value="{{ old('fechaFin',$promocion->fechaFin) }}" required></div>
<div class="campo"><label for="horaInicio">Hora inicial (opcional)</label><input id="horaInicio" name="horaInicio" type="time" value="{{ old('horaInicio',substr($promocion->horaInicio ?? '',0,5)) }}"></div><div class="campo"><label for="horaFin">Hora final (opcional)</label><input id="horaFin" name="horaFin" type="time" value="{{ old('horaFin',substr($promocion->horaFin ?? '',0,5)) }}"></div>
<div class="campo"><label for="activo">Estado</label><select id="activo" name="activo"><option value="1" @selected((int)old('activo',$promocion->activo)===1)>Activa</option><option value="0" @selected((int)old('activo',$promocion->activo)===0)>Inactiva</option></select></div><button class="boton">Guardar promoción</button><a class="boton secundario" href="{{ route('admin.indexpromocion') }}">Volver</a>
</form>
<p>Elige Pedido para aplicar a todos los productos, o marca los productos que quieras incluir.</p>
<script>
const aplica=document.getElementById('aplica'),producto=document.getElementById('producto'),tipo=document.getElementById('tipo'),valor=document.getElementById('valor');
function campos(){
    producto.disabled=aplica.value!=='Producto';
    producto.hidden=producto.disabled;
    const opciones=[...producto.querySelectorAll('input')], cantidad=opciones.filter(c=>c.checked).length;
    document.getElementById('seleccionados').textContent=cantidad+' producto(s) seleccionado(s)';
    if(opciones.length) opciones[0].setCustomValidity(!producto.disabled && !cantidad ? 'Selecciona al menos un producto.' : '');
    valor.max=tipo.value==='Porcentaje' ? 100 : 999999.99;
}
aplica.addEventListener('change',campos);tipo.addEventListener('change',campos);producto.addEventListener('change',campos);campos();
</script>
@endsection
