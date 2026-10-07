@extends('layouts.app')
@section('titulo',$promocion->exists ? 'Editar promoción' : 'Nueva promoción')
@section('contenido')
<h1>{{ $promocion->exists ? 'Editar promoción' : 'Nueva promoción' }}</h1>
@if($errors->any())<div class="aviso error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form class="tarjeta formulario" method="POST" action="{{ $promocion->exists ? route('admin.updatepromocion',$promocion->id) : route('admin.storedpromocion') }}">@csrf
<div class="campo"><label for="nombre">Nombre</label><input id="nombre" name="nombre" maxlength="100" value="{{ old('nombre',$promocion->nombre) }}" required></div>
<div class="campo"><label for="tipo">Tipo</label><select id="tipo" name="tipo" required>@foreach(['Porcentaje','Monto fijo'] as $tipo)<option @selected(old('tipo',$promocion->tipo)===$tipo)>{{ $tipo }}</option>@endforeach</select></div>
<div class="campo"><label for="aplica">Aplica sobre</label><select id="aplica" name="aplicaSobre" required>@foreach(['Pedido','Producto'] as $aplica)<option @selected(old('aplicaSobre',$promocion->aplicaSobre)===$aplica)>{{ $aplica }}</option>@endforeach</select></div>
<div class="campo"><label for="producto">Producto (para promociones de producto)</label><select id="producto" name="producto_id"><option value="">Seleccionar</option>@foreach($productos as $p)<option value="{{ $p->id }}" @selected(old('producto_id',$promocion->producto_id)==$p->id)>{{ $p->nombre }} — stock {{ $p->stock?->stock_actual ?? 0 }}</option>@endforeach</select></div>
<div class="campo"><label for="valor">Valor (% o Bs)</label><input id="valor" name="valor" type="number" min="0.01" step="0.01" max="999999.99" value="{{ old('valor',$promocion->valor) }}" required></div>
<div class="campo"><label for="tope">Tope de descuento (Bs, opcional)</label><input id="tope" name="topeDescuento" type="number" min="0" step="0.01" max="999999.99" value="{{ old('topeDescuento',$promocion->topeDescuento) }}"></div>
<div class="campo"><label for="inicio">Fecha inicial</label><input id="inicio" name="fechaInicio" type="date" value="{{ old('fechaInicio',$promocion->fechaInicio) }}" required></div><div class="campo"><label for="fin">Fecha final</label><input id="fin" name="fechaFin" type="date" value="{{ old('fechaFin',$promocion->fechaFin) }}" required></div>
<div class="campo"><label for="horaInicio">Hora inicial (opcional)</label><input id="horaInicio" name="horaInicio" type="time" value="{{ old('horaInicio',substr($promocion->horaInicio ?? '',0,5)) }}"></div><div class="campo"><label for="horaFin">Hora final (opcional)</label><input id="horaFin" name="horaFin" type="time" value="{{ old('horaFin',substr($promocion->horaFin ?? '',0,5)) }}"></div>
<div class="campo"><label for="activo">Estado</label><select id="activo" name="activo"><option value="1" @selected((int)old('activo',$promocion->activo)===1)>Activa</option><option value="0" @selected((int)old('activo',$promocion->activo)===0)>Inactiva</option></select></div><button class="boton">Guardar promoción</button><a class="boton secundario" href="{{ route('admin.indexpromocion') }}">Volver</a>
</form>
<script>const aplica=document.getElementById('aplica'),producto=document.getElementById('producto'),tipo=document.getElementById('tipo'),valor=document.getElementById('valor');function campos(){producto.required=aplica.value==='Producto';producto.disabled=aplica.value!=='Producto';valor.max=tipo.value==='Porcentaje' ? 100 : 999999.99;}aplica.addEventListener('change',campos);tipo.addEventListener('change',campos);campos();</script>
@endsection
