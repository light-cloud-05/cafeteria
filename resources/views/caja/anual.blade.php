@extends('layouts.app')
@section('titulo', 'Consolidado anual de caja')
@section('contenido')
<div class="caja-panel"><h1>Consolidado anual de caja</h1>
@include('caja._navegacion')
@include('caja._aviso')
@if($errors->any())<div class="aviso error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="GET" action="{{ route('admin.caja.anual') }}" class="tarjeta formulario caja-filtros"><div class="campo"><label for="anio">Año</label><input id="anio" name="anio" type="number" min="1000" max="9999" value="{{ $anio }}" required></div><div class="acciones"><button class="boton">Consultar</button></div></form>
@include('caja._resumen-periodo')
@php($nombres = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'])
<p id="ayuda-tabla-anual">Los meses se construyen con la misma lectura anual de versiones vigentes. Desplaza horizontalmente para ver todas las columnas.</p>
<div class="tarjeta tabla-contenedor caja-tabla caja-listado" role="region" aria-label="Detalle anual por mes" aria-describedby="ayuda-tabla-anual" tabindex="0"><table><caption>Caja {{ $anio }} por mes</caption><thead><tr><th scope="col">Mes</th><th scope="col">Cobertura</th><th scope="col">Conciliación</th><th scope="col">Días cerrados / exigibles</th><th scope="col">Pendientes</th><th scope="col">Total vendido</th><th scope="col">Efectivo esperado</th><th scope="col">QR</th><th scope="col">Efectivo contado</th><th scope="col">Diferencia</th><th scope="col">Días rectificados / con diferencia</th></tr></thead><tbody>
@foreach($resumen['meses'] as $fila)<tr><td><a href="{{ route('admin.caja.mensual', ['anio'=>$anio,'mes'=>$fila['mes']]) }}">{{ $nombres[$fila['mes'] - 1] }}</a></td><td>{{ ucfirst($fila['estado']) }}@if($fila['en_curso']) — en curso @endif @if($fila['fechas_inconsistentes']) — datos inconsistentes @endif</td><td>{{ ucfirst(str_replace('_', ' ', $fila['conciliacion'])) }}</td><td>{{ $fila['dias_cerrados'] }} / {{ $fila['dias_exigibles'] }}</td><td>{{ count($fila['fechas_sin_cierre']) }}</td>
@foreach(['total_ventas','efectivo_esperado','qr_registrado','efectivo_contado','diferencia'] as $campo)<td class="caja-importe">{{ \App\Support\ImporteConsolidadoVista::formatear($fila['totales'][$campo] ?? null) }}</td>@endforeach
<td>{{ $fila['dias_rectificados'] }} / {{ $fila['dias_con_diferencia'] }}</td></tr>@endforeach
</tbody></table></div>
<details class="tarjeta"><summary>Fechas terminadas pendientes de cierre ({{ count($resumen['fechas_sin_cierre']) }})</summary><p class="caja-texto">{{ $resumen['fechas_sin_cierre'] ? implode(', ', $resumen['fechas_sin_cierre']) : 'Sin fechas pendientes de cierre.' }}</p></details>
</div>
@endsection
