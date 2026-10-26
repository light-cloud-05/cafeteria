@extends('layouts.app')
@section('titulo', 'Consulta de caja no disponible')
@section('contenido')
<div class="caja-panel">
<h1>Consulta de caja no disponible</h1>
@include('caja._navegacion')
@include('caja._aviso')
<div class="aviso error" role="alert">No se pudo completar la consulta. No se ha solicitado ninguna operación financiera. Solicita asistencia si el problema continúa.</div>
</div>
@endsection
