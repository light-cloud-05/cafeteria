<p id="ayuda-ventas-reporte">Desplaza la tabla horizontalmente para consultar el desglose completo. El cambio no forma parte del ingreso.</p>
<div class="tarjeta tabla-contenedor" role="region" aria-label="Ventas y componentes de pago" tabindex="0"><table>
<thead><tr><th>Pedido</th><th>Fecha de venta (Bolivia)</th><th>Método</th><th>Efectivo aplicado</th><th>QR aplicado</th><th>Tarjeta aplicada</th><th>Importe</th><th>Origen</th></tr></thead>
<tbody>@forelse($ventas as $v)<tr>
<td>#{{ $v->pedido?->numero_diario ?? $v->pedido_id }}</td><td>{{ $v->fecha_hora }}</td>
<td>{{ match($v->metodo_pago) {'QR','Transferencia QR'=>'QR manual','QR_SIMULADO'=>'QR simulado','TARJETA_SIMULADA'=>'Tarjeta simulada',default=>$v->metodo_pago} }}</td>
@foreach(['monto_efectivo','monto_qr','monto_tarjeta','total'] as $campo)<td>{{ \App\Support\ImporteReporteVista::formatear((string)$v->{$campo}) }}</td>@endforeach
<td>{{ $v->origen === 'SIMULACION_LOCAL' ? 'SIMULACION_LOCAL' : 'Presencial' }}</td></tr>
@empty<tr><td colspan="8">Sin ventas registradas.</td></tr>@endforelse</tbody>
<tfoot><tr><th colspan="3">Total general ({{ $cantidad }} ventas)</th>
@foreach(['monto_efectivo','monto_qr','monto_tarjeta','total'] as $campo)<td>{{ \App\Support\ImporteReporteVista::formatear($totales[$campo]) }}</td>@endforeach<td></td></tr></tfoot>
</table></div>
