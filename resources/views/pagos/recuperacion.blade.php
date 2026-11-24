@extends('layouts.app')
@section('titulo','Consultar solicitud de pago')
@include('pagos._estilos')
@section('contenido')
<div class="pago-demo-contenedor">
<h1>Verifica el intento existente</h1>
@include('pagos._aviso')
<div class="aviso" role="alert">No se pudo confirmar el resultado de la solicitud. Esto no demuestra que la operación haya fallado.</div>
<p>No se ha generado otro intento automáticamente. Consulta el registro original antes de volver a cobrar.</p>
@if($clave)
    <p>Clave de esta solicitud: <code>{{ $clave }}</code></p>
    <a class="boton" href="{{ route('admin.pagos.recuperar',['clave_idempotencia'=>$clave]) }}">Consultar solicitud original</a>
@elseif(isset($referencia))
    <p>Referencia: <code>{{ $referencia }}</code></p>
    <a class="boton" href="{{ route('admin.pagos.show',$referencia) }}">Consultar intento original</a>
@endif
<p>Si aún no aparece un registro, espera y vuelve a consultar. Su ausencia en una lectura no confirma que no se haya guardado. Conserva la misma clave de solicitud.</p>
<a href="{{ route('admin.pagos.historial') }}">Consultar historial</a>
</div>
@endsection
