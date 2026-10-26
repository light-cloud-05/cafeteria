@extends('layouts.app')
@section('titulo', 'Consolidado mensual de caja')
@section('contenido')
<div class="caja-panel"><h1>Consolidado mensual de caja</h1>
@include('caja._navegacion')
@include('caja._aviso')
@if($errors->any())<div class="aviso error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="GET" action="{{ route('admin.caja.mensual') }}" class="tarjeta formulario caja-filtros">
<div class="campo"><label for="anio">Año</label><input id="anio" name="anio" type="number" min="1000" max="9999" value="{{ $anio }}" required></div>
<div class="campo"><label for="mes">Mes</label><select id="mes" name="mes">@foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $indice=>$nombre)<option value="{{ $indice + 1 }}" @selected($mes === $indice + 1)>{{ $nombre }}</option>@endforeach</select></div>
<div class="acciones"><button class="boton">Consultar</button><a class="boton secundario" href="{{ route('admin.caja.anual', ['anio'=>$anio]) }}">Ver el año {{ $anio }}</a></div></form>
@include('caja._resumen-periodo')
@php($estados = ['cerrado'=>'Cerrado','pendiente'=>'Pendiente de cierre','abierto'=>'Día abierto','futuro'=>'Futuro: todavía no corresponde','inconsistente'=>'Cierre inconsistente'])
<p id="ayuda-tabla-mensual">Detalle por fecha operativa. Desplaza horizontalmente para ver todas las columnas.</p>
<div class="tarjeta tabla-contenedor caja-tabla caja-listado" role="region" aria-label="Detalle mensual por fecha operativa" aria-describedby="ayuda-tabla-mensual" tabindex="0"><table><caption>Caja {{ $anio }}-{{ sprintf('%02d', $mes) }}: solo versiones vigentes</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col">Versión</th><th scope="col">Total vendido</th><th scope="col">Efectivo esperado</th><th scope="col">QR</th><th scope="col">Efectivo contado</th><th scope="col">Diferencia</th></tr></thead><tbody>
@foreach($resumen['dias'] as $dia)<tr><td>@if($dia['cierre_id'])<a href="{{ route('admin.caja.show', $dia['cierre_id']) }}">{{ $dia['fecha'] }}</a>@else{{ $dia['fecha'] }}@endif</td><td>{{ $estados[$dia['estado']] }}</td><td>@if($dia['version_id'])<a href="{{ route('admin.caja.version', [$dia['cierre_id'], $dia['version_id']]) }}">V{{ $dia['numero_version'] }}</a>@if($dia['numero_version'] > 1) — rectificado @endif @else Sin versión @endif</td>
@foreach(['total_ventas','efectivo_esperado','qr_registrado','efectivo_contado','diferencia'] as $campo)<td class="caja-importe">{{ \App\Support\ImporteConsolidadoVista::formatear($dia['totales'][$campo] ?? null) }}</td>@endforeach</tr>@endforeach
</tbody></table></div></div>
@endsection
