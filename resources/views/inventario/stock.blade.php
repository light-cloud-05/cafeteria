@extends('layouts.app')
@section('titulo','Inventario')
@section('contenido')
<h1>{{ ['actual'=>'Stock actual','minimos'=>'Productos con stock mínimo','agotados'=>'Avisos de productos agotados'][$modo] }}</h1>
<form method="GET"><div class="campo"><label>Categoría</label><select name="categoria_id"><option value="">Todas</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected(request('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>@endforeach</select></div><button class="boton">Filtrar</button></form>
<div class="tarjeta tabla-contenedor"><table><thead><tr><th>Producto</th><th>Categoría</th><th>Stock actual</th><th>Mínimo</th><th>Estado</th></tr></thead><tbody>
@forelse($productos as $producto)
<tr><td>{{ $producto->nombre }}</td><td>{{ $producto->categoria?->nombre }}</td><td>{{ $producto->stock?->stock_actual ?? 'Sin registro' }}</td><td>{{ $producto->stock?->stock_minimo ?? 'Sin definir' }}</td><td><span class="etiqueta {{ $producto->vendible() ? 'activa' : 'inactiva' }}">{{ $producto->vendible() ? 'Disponible' : ($producto->stock && $producto->stock->stock_actual <= $producto->stock->stock_minimo ? 'Agotado / requiere reposición' : 'No disponible') }}</span></td></tr>
@empty<tr><td colspan="5">{{ $modo === 'minimos' ? 'Todos los productos tienen stock suficiente.' : ($modo === 'agotados' ? 'No hay productos agotados.' : 'No hay productos en el inventario.') }}</td></tr>@endforelse
</tbody></table></div>
@if(auth()->user()->tienePermiso('registrar_ajustes'))
@if($modo === 'actual')
<div class="tarjeta">
    <h2>Definir stock actual</h2>
    <p>Ingresa la cantidad total que tiene el producto. El sistema registra la diferencia como un ajuste de inventario.</p>
    @foreach($productos as $producto)
        <form method="POST" action="{{ route('admin.movimiento.store.ajuste') }}" class="formulario">
            @csrf
            <input type="hidden" name="producto_id" value="{{ $producto->id }}">
            <input type="hidden" name="volver_stock" value="1">
            <input type="hidden" name="fecha_hora" value="{{ now()->format('Y-m-d H:i:s') }}">
            <h3>{{ $producto->nombre }}</h3>
            <div class="campo">
                <label for="stock-{{ $producto->id }}">Stock actual</label>
                <input id="stock-{{ $producto->id }}" name="cantidad" type="number" min="0" step="1" max="99999999" value="{{ (string) old('producto_id') === (string) $producto->id ? old('cantidad', $producto->stock?->stock_actual ?? 0) : ($producto->stock?->stock_actual ?? 0) }}" required>
            </div>
            <div class="campo">
                <label for="motivo-{{ $producto->id }}">Motivo del ajuste</label>
                <input id="motivo-{{ $producto->id }}" name="motivo" maxlength="255" value="{{ (string) old('producto_id') === (string) $producto->id ? old('motivo', 'Definición de stock actual') : 'Definición de stock actual' }}" required>
            </div>
            <button class="boton">Guardar stock actual</button>
        </form>
    @endforeach
</div>
@endif
<div class="tarjeta"><h2>Definir stock mínimo</h2>@foreach($productos as $producto)<form method="POST" action="{{ route('admin.stock.minimo',$producto->id) }}">@csrf @method('PUT')<label>{{ $producto->nombre }} <input name="stock_minimo" type="number" min="0" step="1" max="99999999" value="{{ $producto->stock?->stock_minimo ?? 0 }}" required></label><button class="boton secundario">Guardar mínimo</button></form>@endforeach</div>
@endif
@if($errors->any())<div class="aviso error">{{ $errors->first() }}</div>@endif
@endsection
