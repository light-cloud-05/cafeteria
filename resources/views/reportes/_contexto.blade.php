@if($simulacion || \App\Services\Caja\AislamientoCajaDemo::solicitada())
@include('pagos._aviso')
@endif
<p>Solo ventas completadas. Los intentos rechazados, pendientes, cancelados y expirados no son ventas.</p>
