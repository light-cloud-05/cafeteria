@extends('layouts.app')
@section('titulo', 'Consulta de caja')
@section('contenido')
<div class="caja-panel">
<h1>Consulta de caja</h1>
@include('caja._navegacion')
@include('caja._aviso')
<p><a class="boton secundario" href="{{ route('admin.caja.previsualizacion') }}">Previsualizar un día</a></p>
@if($errors->any())<div class="aviso error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="GET" action="{{ route('admin.caja.index') }}" class="tarjeta formulario caja-filtros">
<div class="campo"><label for="tipo">Registros</label><select id="tipo" name="tipo">@foreach(['oficiales'=>'Cierres oficiales','historicos'=>'Históricos sin fecha operativa','todos'=>'Todos los registros'] as $valor=>$texto)<option value="{{ $valor }}" @selected($tipo === $valor)>{{ $texto }}</option>@endforeach</select></div>
<div class="campo"><label for="desde">Fecha operativa desde</label><input id="desde" name="desde" type="date" value="{{ $filtros['desde'] ?? '' }}"></div>
<div class="campo"><label for="hasta">Fecha operativa hasta</label><input id="hasta" name="hasta" type="date" value="{{ $filtros['hasta'] ?? '' }}"></div>
<div class="campo"><label for="por-pagina">Registros por página</label><select id="por-pagina" name="por_pagina">@foreach([10,20,50] as $cantidad)<option value="{{ $cantidad }}" @selected((int)($filtros['por_pagina'] ?? 20) === $cantidad)>{{ $cantidad }}</option>@endforeach</select></div>
<div class="acciones"><button class="boton">Consultar</button><a class="boton secundario" href="{{ route('admin.caja.index') }}">Limpiar filtros</a></div>
</form>
<p>Los filtros de fecha operativa excluyen los históricos sin fecha.</p>
<p id="ayuda-tabla-cierres">En pantallas pequeñas, desplaza la tabla horizontalmente para consultar todos los importes.</p>
<div class="tarjeta tabla-contenedor caja-tabla caja-listado" role="region" aria-label="Cierres y versión vigente" aria-describedby="ayuda-tabla-cierres" tabindex="0"><table><caption>Cierres y versión vigente</caption><thead><tr><th scope="col">Registro</th><th scope="col">Fecha / tipo</th><th scope="col">Versión</th><th scope="col">Responsable</th><th scope="col">Total vendido</th><th scope="col">Efectivo esperado</th><th scope="col">QR</th><th scope="col">Efectivo contado</th><th scope="col">Diferencia</th></tr></thead><tbody>
@forelse($cierres as $cierre)
@php($vigente = $cierre->versionVigente)
<tr><td><a href="{{ route('admin.caja.show', $cierre->id) }}">Ver #{{ $cierre->id }}</a></td><td>@if($cierre->fecha_operativa)<span class="etiqueta activa">Oficial</span><p>{{ $cierre->fecha_operativa->format('d/m/Y') }}</p>@else<span class="etiqueta inactiva">Histórico no oficial</span>@endif</td><td>{{ $vigente?->numero_version ?? 'Sin versión' }}</td><td>{{ $vigente?->responsable?->nombreCompleto ?? $cierre->usuario?->nombreCompleto ?? 'No disponible' }}</td>
@foreach([$vigente?->total_ventas, $vigente?->efectivo_esperado, $vigente?->qr_registrado, $vigente?->efectivo_contado, $vigente?->diferencia] as $importe)<td class="caja-importe">{{ \App\Support\ImporteCajaVista::formatear($importe) }}</td>@endforeach</tr>
@empty<tr><td class="caja-vacio" colspan="9">No hay cierres para los filtros seleccionados.</td></tr>@endforelse
</tbody></table></div>
@include('caja._paginacion', ['paginador' => $cierres, 'etiqueta' => 'Páginas de cierres'])
</div>
@endsection
