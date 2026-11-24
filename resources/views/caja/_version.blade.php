<div class="tarjeta"><h2>Versión {{ $version->numero_version }}</h2><dl class="caja-datos">
<div><dt>Estado de la versión</dt><dd><span class="etiqueta {{ $cierre->versionVigente?->id === $version->id ? 'activa' : 'inactiva' }}">{{ $cierre->versionVigente?->id === $version->id ? 'Vigente' : 'Histórica conservada' }}</span></dd></div>
<div><dt>Responsable</dt><dd>{{ $version->responsable?->nombreCompleto ?? 'No disponible' }}</dd></div>
<div><dt>Registrada en Bolivia</dt><dd>{{ $version->registrado_en?->format('d/m/Y H:i:s') }}</dd></div>
@foreach(['total_ventas'=>'Total vendido','efectivo_esperado'=>'Efectivo esperado','qr_registrado'=>'QR registrado','tarjeta_registrado'=>'Tarjeta registrada','efectivo_contado'=>'Efectivo contado','diferencia'=>'Diferencia (contado menos esperado)'] as $campo=>$titulo)<div><dt>{{ $titulo }}</dt><dd class="caja-importe">{{ \App\Support\ImporteCajaVista::formatear($version->{$campo}) }}</dd></div>@endforeach
<div><dt>Observación</dt><dd class="caja-texto">{{ $version->observacion ?? 'Sin observación' }}</dd></div><div><dt>Motivo de rectificación</dt><dd class="caja-texto">{{ $version->motivo_correccion ?? 'Versión inicial' }}</dd></div>
</dl></div>
<p id="ayuda-tabla-ventas">Importes guardados en esta versión. Desplaza la tabla para consultar todas las columnas.</p>
<div class="tarjeta tabla-contenedor caja-tabla" role="region" aria-label="Instantáneas históricas de ventas" aria-describedby="ayuda-tabla-ventas" tabindex="0"><table><caption>Instantáneas históricas de ventas: {{ $detalles->total() }}</caption><thead><tr><th scope="col">Venta</th><th scope="col">Cobro en Bolivia</th><th scope="col">Método registrado</th><th scope="col">Total</th><th scope="col">Efectivo</th><th scope="col">QR</th><th scope="col">Tarjeta</th><th scope="col">Origen</th></tr></thead><tbody>
@forelse($detalles as $detalle)<tr><td>#{{ $detalle->venta_id }}</td><td>{{ $detalle->fecha_cobro?->format('d/m/Y H:i:s') }}</td><td>{{ $detalle->metodo_pago }}</td>
@foreach([$detalle->total, $detalle->monto_efectivo, $detalle->monto_qr, $detalle->monto_tarjeta] as $importe)<td class="caja-importe">{{ \App\Support\ImporteCajaVista::formatear($importe) }}</td>@endforeach<td>{{ $detalle->origen === 'SIMULACION_LOCAL' ? 'SIMULACION_LOCAL' : 'Presencial' }}</td></tr>
@empty<tr><td class="caja-vacio" colspan="8">Esta versión no incluye ventas.</td></tr>@endforelse
</tbody></table></div>
@include('caja._paginacion', ['paginador' => $detalles, 'etiqueta' => 'Páginas de instantáneas de ventas'])
