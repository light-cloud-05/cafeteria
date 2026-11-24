@if(\App\Services\Caja\AislamientoCajaDemo::solicitada() || \App\Services\Pagos\ContextoLecturaPagos::demostracionDisponible())
<div class="aviso caja-aviso" role="status"><strong>DEMOSTRACIÓN ACADÉMICA — DATOS FICTICIOS</strong><br>Cierres de prueba en una base aislada. Horario de Bolivia (America/La_Paz).</div>
@include('pagos._aviso')
@else
<div class="aviso caja-aviso" role="status"><strong>Las operaciones financieras de caja todavía no están habilitadas</strong><br>Consultas en horario de Bolivia (America/La_Paz). Este módulo permite únicamente consultas.</div>
@endif
