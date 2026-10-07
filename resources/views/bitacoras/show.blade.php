@extends('layouts.app')
@section('titulo','Evento de bitácora #'.$bitacora->id)
@section('contenido')
<h1>Evento de bitácora #{{ $bitacora->id }}</h1>
<div class="tarjeta"><dl><dt>Fecha y hora de Bolivia</dt><dd>{{ $bitacora->fechaHora?->format('d/m/Y H:i:s') }}</dd><dt>Usuario</dt><dd>{{ $bitacora->usuario?->nombreUsuario ?? 'No disponible' }} — {{ $bitacora->usuario?->nombreCompleto }}</dd><dt>Acción</dt><dd>{{ str_replace('_',' ',$bitacora->accion) }}</dd><dt>Entidad / tabla</dt><dd>{{ $bitacora->tablaAfectada }}</dd><dt>Registro afectado</dt><dd>{{ $bitacora->idRegistroAfectado }}</dd><dt>Dirección IP</dt><dd>{{ $bitacora->ip ?? 'No registrada' }}</dd></dl></div>
<div class="tarjeta"><h2>Valores anteriores</h2><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $anteriores }}</pre><h2>Valores nuevos</h2><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $nuevos }}</pre></div>
<a class="boton secundario" href="{{ route('admin.indexbitacora') }}">Volver a la bitácora</a>
@endsection
