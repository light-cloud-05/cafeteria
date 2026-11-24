@if(app(\App\Support\DisponibilidadPagosSimulados::class)->habilitada())
@include('pagos._estilos')
<section class="tarjeta pago-demo-acceso" aria-labelledby="cu26-titulo">
    <h2 id="cu26-titulo">CU26 · Pagos de demostración</h2>
    @include('pagos._aviso')
    @php($activoDemo = $pedido->transaccionesPago()->where('estado','PENDIENTE')->first())
    @if($activoDemo)
        <p>Este pedido tiene un intento pendiente. Debes resolverlo antes de editar o cobrar por otro medio.</p>
        <a class="boton" href="{{ route('admin.pagos.show', $activoDemo->referencia) }}">Consultar intento pendiente</a>
    @elseif($pedido->estado === 'Registrado' && !$pedido->venta()->exists())
        <div class="acciones">
            <a class="boton" href="{{ route('admin.pagos.crear', ['id'=>$pedido->id,'medio'=>'QR_SIMULADO']) }}">QR simulado</a>
            <a class="boton secundario" href="{{ route('admin.pagos.crear', ['id'=>$pedido->id,'medio'=>'TARJETA_SIMULADA']) }}">Tarjeta simulada</a>
        </div>
    @endif
    <p><a href="{{ route('admin.pagos.historial', ['pedido'=>$pedido->id]) }}">Historial de transacciones de este pedido</a></p>
</section>
@endif
